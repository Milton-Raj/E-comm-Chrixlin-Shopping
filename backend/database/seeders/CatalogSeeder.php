<?php

namespace Database\Seeders;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Inventory\InventoryService;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DigitalFile;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TaxClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo catalog (development only): 7 categories, 5 brands, 21 products incl. 5 digital,
 * with variants, opening stock, images and sample downloadable PDFs.
 * Source data: database/seeders/data/catalog.json; images: seeders/assets/demo (Pexels).
 */
class CatalogSeeder extends Seeder
{
    public function run(InventoryService $inventory): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('CatalogSeeder seeds demo products and must not run in production.');
        }

        $data = json_decode((string) file_get_contents(database_path('seeders/data/catalog.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->copyImages();

        $categories = [];
        foreach ($data['categories'] as $i => $c) {
            $categories[$c['slug']] = Category::query()->updateOrCreate(['slug' => $c['slug']], [
                'name' => $c['name'], 'description' => $c['description'], 'sort_order' => $i, 'is_active' => true,
                'image_path' => ltrim($c['image'], '/'),
            ]);
        }

        $taxClasses = TaxClass::query()->get()->keyBy('slug');
        $taxFor = fn (string $category, string $type) => match (true) {
            $type === 'digital' => $taxClasses['standard'],
            $category === 'jewellery' => $taxClasses['low'],
            $category === 'apparel' => $taxClasses['reduced'],
            default => $taxClasses['standard'],
        };

        foreach ($data['products'] as $i => $p) {
            $brand = Brand::query()->updateOrCreate(['slug' => $p['brand']['slug']], ['name' => $p['brand']['name'], 'is_active' => true]);
            $type = ProductType::from($p['product_type']);

            $product = Product::query()->updateOrCreate(['slug' => $p['slug']], [
                'product_type' => $type,
                'status' => ProductStatus::Active,
                'name' => $p['name'],
                'short_description' => $p['short_description'],
                'description' => implode("\n\n", $p['description']),
                'brand_id' => $brand->id,
                'category_id' => $categories[$p['category']['slug']]->id,
                'tax_class_id' => $taxFor($p['category']['slug'], $p['product_type'])->id,
                'currency' => $p['price']['currency'],
                'options' => $p['options'],
                'specifications' => $p['specifications'],
                'is_featured' => $p['is_featured'],
                'is_new' => $p['is_new'],
                'is_best_seller' => $p['is_best_seller'],
                'rating_avg' => $p['rating']['average'],
                'rating_count' => $p['rating']['count'],
                'published_at' => now()->subDays($p['sort_rank'] * 3),
            ]);

            if ($product->variants()->exists()) {
                continue; // idempotent re-run
            }

            foreach ($p['images'] as $j => $image) {
                $product->media()->create(['disk' => 'public', 'path' => ltrim($image['url'], '/'), 'alt_text' => $image['alt'], 'sort_order' => $j]);
            }

            $combos = $this->combinations($p['options']);
            $stockEach = $p['stock_status'] === 'low_stock' ? 2 : 12;
            foreach ($combos as $k => $combo) {
                $suffix = $combo ? '-'.implode('-', array_map(fn ($v) => Str::upper(Str::substr(Str::slug($v), 0, 4)), $combo)) : '';
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => Str::upper(Str::substr(Str::slug($p['brand']['name'], ''), 0, 3)).'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT).$suffix,
                    'name' => $combo ? implode(' / ', $combo) : null,
                    'options' => $combo ?: null,
                    'price' => $p['price']['amount'],
                    'compare_at_price' => $p['compare_at_price']['amount'] ?? null,
                    'cost_price' => intdiv($p['price']['amount'] * 45, 100),
                    'track_inventory' => $type->tracksInventory(),
                    'is_default' => $k === 0,
                    'sort_order' => $k,
                ]);

                if ($type->tracksInventory()) {
                    $variant->inventory()->create(['on_hand' => 0, 'reserved' => 0]);
                    $inventory->adjust($variant, 'opening', $stockEach, 'Opening stock (demo)');
                }
            }

            if ($type === ProductType::Digital) {
                $product->digital()->updateOrCreate([], [
                    'download_limit' => $p['digital']['download_limit'],
                    'access_days' => $p['digital']['access_days'],
                    'format' => $p['digital']['format'],
                ]);
                $this->sampleFile($product);
            }

            $product->refreshPriceRange();
        }
    }

    private function copyImages(): void
    {
        $disk = Storage::disk('public');
        foreach (glob(database_path('seeders/assets/demo/*.jpg')) ?: [] as $file) {
            $target = 'demo/'.basename($file);
            if (! $disk->exists($target)) {
                $disk->put($target, (string) file_get_contents($file));
            }
        }
    }

    /**
     * @param  list<array{name: string, values: list<string>}>  $options
     * @return list<array<string, string>>
     */
    private function combinations(array $options): array
    {
        $result = [[]];
        foreach ($options as $option) {
            $next = [];
            foreach ($result as $partial) {
                foreach ($option['values'] as $value) {
                    $next[] = $partial + [$option['name'] => $value];
                }
            }
            $result = $next;
        }

        return $result === [[]] ? [[]] : $result;
    }

    /** A small, valid PDF so the full digital-delivery flow can be tested end to end. */
    private function sampleFile(Product $product): void
    {
        $text = "{$product->name} - sample edition. Thank you for your purchase.";
        $pdf = $this->pdf($text);
        $path = "digital/{$product->uuid}/".Str::slug($product->name).'-sample.pdf';
        Storage::disk('local')->put($path, $pdf);

        DigitalFile::query()->firstOrCreate(['product_id' => $product->id, 'path' => $path], [
            'disk' => 'local', 'original_name' => Str::slug($product->name).'-sample.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => strlen($pdf), 'checksum_sha256' => hash('sha256', $pdf), 'is_active' => true,
        ]);
    }

    private function pdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman >>',
        ];
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $n => $body) {
            $offsets[] = strlen($out);
            $out .= ($n + 1)." 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $out .= sprintf("%010d 00000 n \n", $offset);
        }

        return $out.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
