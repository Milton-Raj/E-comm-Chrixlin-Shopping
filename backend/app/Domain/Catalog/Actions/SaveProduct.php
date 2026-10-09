<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Inventory\InventoryService;
use App\Exceptions\ApiException;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxClass;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Storefront\StorefrontCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Creates or updates a product with its variants and digital settings in one
 * transaction (PRD §43). New variants may carry opening stock; later stock changes
 * go through the inventory ledger, never by editing numbers here.
 */
class SaveProduct
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Product $product, Request $request, User $actor): Product
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('products', 'slug')->ignore($product->id)],
            'product_type' => [$product->exists ? 'sometimes' : 'required', Rule::in(array_map(fn ($t) => $t->value, ProductType::enabled()))],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:20000'],
            'brand' => ['nullable', 'uuid', Rule::exists('brands', 'uuid')],
            'category' => ['nullable', 'uuid', Rule::exists('categories', 'uuid')],
            'tax_class' => ['nullable', 'uuid', Rule::exists('tax_classes', 'uuid')],
            'options' => ['nullable', 'array', 'max:3'],
            'options.*.name' => ['required', 'string', 'max:40'],
            'options.*.values' => ['required', 'array', 'min:1', 'max:20'],
            'options.*.values.*' => ['required', 'string', 'max:40'],
            'specifications' => ['nullable', 'array', 'max:30'],
            'specifications.*.label' => ['required', 'string', 'max:60'],
            'specifications.*.value' => ['required', 'string', 'max:200'],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
            'is_best_seller' => ['boolean'],
            'free_shipping' => ['boolean'],
            'seo_title' => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'variants' => ['required', 'array', 'min:1', 'max:100'],
            'variants.*.uuid' => ['nullable', 'uuid'],
            'variants.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'variants.*.name' => ['nullable', 'string', 'max:120'],
            'variants.*.options' => ['nullable', 'array'],
            'variants.*.price' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'variants.*.compare_at_price' => ['nullable', 'integer', 'min:0', 'gt:variants.*.price'],
            'variants.*.cost_price' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['boolean'],
            'variants.*.opening_stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'digital' => ['nullable', 'array'],
            'digital.download_limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'digital.access_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'digital.format' => ['nullable', 'string', 'max:80'],
        ]);

        return DB::transaction(function () use ($product, $data, $actor) {
            $before = $product->exists ? $product->only(['name', 'status', 'min_price', 'max_price', 'slug']) : null;
            $type = $product->exists ? $product->product_type : ProductType::from($data['product_type']);

            $product->fill([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? ($product->slug ?: $this->uniqueSlug($data['name'])),
                'status' => $data['status'],
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'brand_id' => isset($data['brand']) ? Brand::query()->where('uuid', $data['brand'])->value('id') : null,
                'category_id' => isset($data['category']) ? Category::query()->where('uuid', $data['category'])->value('id') : null,
                'tax_class_id' => isset($data['tax_class']) ? TaxClass::query()->where('uuid', $data['tax_class'])->value('id') : TaxClass::default()?->id,
                'options' => $data['options'] ?? [],
                'specifications' => $data['specifications'] ?? [],
                'is_featured' => $data['is_featured'] ?? false,
                'is_new' => $data['is_new'] ?? false,
                'is_best_seller' => $data['is_best_seller'] ?? false,
                'free_shipping' => $data['free_shipping'] ?? false,
                'seo_title' => $data['seo_title'] ?? null,
                'seo_description' => $data['seo_description'] ?? null,
            ]);
            $product->product_type = $type;
            $product->currency = $product->currency ?: (string) config('commerce.store.currency');
            if ($data['status'] === ProductStatus::Active->value && ! $product->published_at) {
                $product->published_at = now();
            }
            $product->save();

            $this->syncVariants($product, $data['variants'], $type, $actor);

            if ($type === ProductType::Digital) {
                $product->digital()->updateOrCreate([], [
                    'download_limit' => $data['digital']['download_limit'] ?? 5,
                    'access_days' => $data['digital']['access_days'] ?? null,
                    'format' => $data['digital']['format'] ?? null,
                ]);
            }

            $product->refreshPriceRange();
            Audit::record($before ? 'product.updated' : 'product.created', $product, $before, $product->only(['name', 'status', 'min_price', 'max_price', 'slug']), $actor);
            StorefrontCache::invalidate(['catalog', "product:{$product->slug}"]);

            return $product;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     */
    private function syncVariants(Product $product, array $variants, ProductType $type, User $actor): void
    {
        $keep = [];
        foreach ($variants as $i => $row) {
            $variant = isset($row['uuid'])
                ? $product->variants()->where('uuid', $row['uuid'])->first() ?? throw new ApiException('Unknown variant.', 422, 'variant_unknown')
                : new ProductVariant(['product_id' => $product->id]);

            if (ProductVariant::withTrashed()->where('sku', $row['sku'])->where('id', '!=', $variant->id ?? 0)->exists()) {
                throw new ApiException('Validation failed', 422, null, ["variants.{$i}.sku" => ['This SKU is already used by another product.']]);
            }

            $isNew = ! $variant->exists;
            $variant->fill([
                'sku' => $row['sku'],
                'name' => $row['name'] ?? null,
                'options' => $row['options'] ?? null,
                'price' => $row['price'],
                'compare_at_price' => $row['compare_at_price'] ?? null,
                'cost_price' => $row['cost_price'] ?? null,
                'is_active' => $row['is_active'] ?? true,
                'track_inventory' => $type->tracksInventory(),
                'is_default' => $i === 0,
                'sort_order' => $i,
            ])->save();
            $keep[] = $variant->id;

            if ($isNew && $type->tracksInventory()) {
                $variant->inventory()->create(['on_hand' => 0, 'reserved' => 0]);
                if (($row['opening_stock'] ?? 0) > 0) {
                    $this->inventory->adjust($variant, 'opening', (int) $row['opening_stock'], 'Opening stock', $actor);
                }
            }
        }

        // Variants removed from the form are retired (soft-deleted), never destroyed.
        $product->variants()->whereNotIn('id', $keep)->get()->each(fn (ProductVariant $v) => $v->forceFill(['is_active' => false])->save() && $v->delete());
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $n = 2;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
