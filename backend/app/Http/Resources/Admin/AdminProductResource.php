<?php

namespace App\Http\Resources\Admin;

use App\Models\DigitalFile;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view of a product, including stock numbers and cost (never exposed publicly).
 *
 * @mixin Product
 */
class AdminProductResource extends JsonResource
{
    public bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $variants = $this->variants;
        $stock = $variants->sum(fn (ProductVariant $v) => $v->inventory->on_hand ?? 0);
        $available = $variants->sum(fn (ProductVariant $v) => $v->inventory ? $v->inventory->available() : 0);

        $data = [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_type' => $this->product_type->value,
            'status' => $this->status->value,
            'brand' => $this->brand ? ['uuid' => $this->brand->uuid, 'name' => $this->brand->name] : null,
            'category' => $this->category ? ['uuid' => $this->category->uuid, 'name' => $this->category->name] : null,
            'price' => Money::of($this->min_price, $this->currency)->toArray(),
            'max_price' => Money::of($this->max_price, $this->currency)->toArray(),
            'image' => ($m = $this->media->first()) ? $m->url() : null,
            'variant_count' => $variants->count(),
            'stock_on_hand' => $this->product_type->tracksInventory() ? $stock : null,
            'stock_available' => $this->product_type->tracksInventory() ? $available : null,
            'units_sold' => $this->units_sold,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->detailed) {
            $data += [
                'short_description' => $this->short_description,
                'description' => $this->description,
                'tax_class' => $this->taxClass ? ['uuid' => $this->taxClass->uuid, 'name' => $this->taxClass->name] : null,
                'options' => $this->options ?? [],
                'specifications' => $this->specifications ?? [],
                'is_featured' => $this->is_featured,
                'is_new' => $this->is_new,
                'is_best_seller' => $this->is_best_seller,
                'free_shipping' => $this->free_shipping,
                'seo_title' => $this->seo_title,
                'seo_description' => $this->seo_description,
                'published_at' => $this->published_at?->toIso8601String(),
                'media' => $this->media->map(fn (ProductMedia $m) => ['uuid' => $m->uuid, 'url' => $m->url(), 'alt_text' => $m->alt_text])->values(),
                'variants' => $variants->map(fn (ProductVariant $v) => [
                    'uuid' => $v->uuid, 'sku' => $v->sku, 'name' => $v->name, 'options' => $v->options ?? (object) [],
                    'price' => $v->price, 'compare_at_price' => $v->compare_at_price, 'cost_price' => $v->cost_price, 'is_active' => $v->is_active,
                    'on_hand' => $v->inventory?->on_hand, 'reserved' => $v->inventory?->reserved,
                ])->values(),
                'digital' => $this->digital ? $this->digital->only(['download_limit', 'access_days', 'format']) : null,
                'files' => $this->files->map(fn (DigitalFile $f) => ['uuid' => $f->uuid, 'name' => $f->original_name, 'size_bytes' => $f->size_bytes, 'mime_type' => $f->mime_type, 'created_at' => $f->created_at?->toIso8601String()])->values(),
            ];
        }

        return $data;
    }
}
