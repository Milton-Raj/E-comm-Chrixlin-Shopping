<?php

namespace App\Http\Resources;

use App\Domain\Marketing\MarketingSettings;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Support\Media\YouTube;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Public product shape (API.md §3.3); mirrored by frontend/features/catalog/types.ts.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
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
        $variants = $this->relationLoaded('variants') ? $this->variants->where('is_active', true)->values() : collect();
        $available = $this->availableUnits($variants);
        $lowStockAt = app(MarketingSettings::class)->lowStockCount();
        $stock = $this->stockStatus($available, $lowStockAt);

        $data = [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'product_type' => $this->product_type->value,
            'brand' => $this->brand ? ['slug' => $this->brand->slug, 'name' => $this->brand->name] : null,
            'category' => $this->category ? ['slug' => $this->category->slug, 'name' => $this->category->name] : null,
            'short_description' => $this->short_description,
            'price' => Money::of($this->min_price, $this->currency)->toArray(),
            'compare_at_price' => $this->compare_at_price && $this->compare_at_price > $this->min_price ? Money::of($this->compare_at_price, $this->currency)->toArray() : null,
            'rating' => ['average' => round($this->rating_avg, 1), 'count' => $this->rating_count],
            'stock_status' => $stock,
            // Exact count only for honest scarcity ("Only 3 left"), at or below the owner's threshold.
            'low_stock_count' => $stock === 'low_stock' ? $available : null,
            // One-tap "Add" in suggestions: only for a single, available variant (anything else needs the product page).
            'quick_add_variant' => $variants->count() === 1 && $stock !== 'out_of_stock' ? $variants->first()->uuid : null,
            'is_new' => $this->is_new,
            'is_best_seller' => $this->is_best_seller,
            'free_shipping' => $this->free_shipping,
            'is_featured' => $this->is_featured,
            'images' => $this->media->map(fn (ProductMedia $m) => ['url' => $m->url(), 'alt' => $m->alt_text ?? $this->name])->values(),
            // Sitemap <lastmod>: tells search engines which products are new or changed.
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->detailed) {
            $data += [
                'description' => array_values(array_filter(preg_split('/\R{2,}/', (string) $this->description) ?: [])),
                'options' => $this->options ?? [],
                'specifications' => $this->specifications ?? [],
                // Optional YouTube videos, shown after the images in the gallery (loaded only on tap).
                'videos' => $this->relationLoaded('videos')
                    ? $this->videos->map(fn (ProductMedia $m) => ['youtube_id' => $m->video_id, 'thumbnail' => YouTube::thumbnail((string) $m->video_id)])->values()
                    : [],
                'variants' => $variants->map(fn (ProductVariant $v) => [
                    'uuid' => $v->uuid,
                    'sku' => $v->sku,
                    'name' => $v->name,
                    'options' => (object) ($v->options ?? []),
                    'price' => Money::of($v->price, $this->currency)->toArray(),
                    'compare_at_price' => $v->compare_at_price ? Money::of($v->compare_at_price, $this->currency)->toArray() : null,
                    'available' => $v->available() === null || $v->available() > 0,
                ])->values(),
                'digital' => $this->digital ? [
                    'format' => $this->digital->format,
                    'file_size' => $this->fileSizeLabel(),
                    'download_limit' => $this->digital->download_limit,
                    'access_days' => $this->digital->access_days,
                ] : null,
                'seo' => ['title' => $this->seo_title ?? $this->name, 'description' => $this->seo_description ?? $this->short_description],
            ];
        }

        return $data;
    }

    /**
     * Exact stock counts are exposed only at or below the owner's low-stock threshold (API.md §3.3).
     */
    private function stockStatus(?int $available, int $lowStockAt): string
    {
        return match (true) {
            $available === null => 'in_stock',
            $available <= 0 => 'out_of_stock',
            $available <= $lowStockAt => 'low_stock',
            default => 'in_stock',
        };
    }

    /**
     * Units available across active variants, or null when stock is not tracked.
     *
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function availableUnits(Collection $variants): ?int
    {
        if (! $this->product_type->tracksInventory() || $variants->isEmpty()) {
            return null;
        }

        return (int) $variants->sum(fn (ProductVariant $v) => $v->available() ?? 0);
    }

    private function fileSizeLabel(): ?string
    {
        if (! $this->relationLoaded('files')) {
            return null;
        }
        $bytes = (int) $this->files->where('is_active', true)->sum('size_bytes');

        return $bytes > 0 ? ($bytes >= 1_048_576 ? round($bytes / 1_048_576, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB') : null;
    }
}
