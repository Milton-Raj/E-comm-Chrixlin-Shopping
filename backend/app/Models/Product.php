<?php

namespace App\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property ProductType $product_type
 * @property ProductStatus $status
 * @property string $name
 * @property string $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property int|null $brand_id
 * @property int|null $category_id
 * @property int|null $tax_class_id
 * @property string $currency
 * @property int $min_price
 * @property int $max_price
 * @property int|null $compare_at_price
 * @property list<array{name: string, values: list<string>}>|null $options
 * @property list<array{label: string, value: string}>|null $specifications
 * @property bool $is_featured
 * @property bool $is_new
 * @property bool $is_best_seller
 * @property bool $free_shipping
 * @property float $rating_avg
 * @property int $rating_count
 * @property int $units_sold
 * @property Carbon|null $published_at
 * @property-read Brand|null $brand
 * @property-read Category|null $category
 * @property-read TaxClass|null $taxClass
 * @property-read DigitalProduct|null $digital
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, ProductMedia> $media
 * @property-read Collection<int, DigitalFile> $files
 */
class Product extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = [
        'product_type', 'status', 'name', 'slug', 'short_description', 'description', 'brand_id', 'category_id',
        'tax_class_id', 'currency', 'options', 'specifications', 'is_featured', 'is_new', 'is_best_seller', 'free_shipping',
        'rating_avg', 'rating_count', 'seo_title', 'seo_description', 'published_at',
    ];

    protected $attributes = ['min_price' => 0, 'max_price' => 0, 'rating_avg' => 0, 'rating_count' => 0, 'units_sold' => 0];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'status' => ProductStatus::class,
            'options' => 'array',
            'specifications' => 'array',
            'is_featured' => 'boolean',
            'is_new' => 'boolean',
            'is_best_seller' => 'boolean',
            'free_shipping' => 'boolean',
            'rating_avg' => 'float',
            'min_price' => 'integer',
            'max_price' => 'integer',
            'compare_at_price' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @param Builder<Product> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function requiresShipping(): bool
    {
        return $this->product_type->requiresShipping();
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<TaxClass, $this> */
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    /** @return HasOne<DigitalProduct, $this> */
    public function digital(): HasOne
    {
        return $this->hasOne(DigitalProduct::class);
    }

    /** @return HasMany<DigitalFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(DigitalFile::class);
    }

    /**
     * Keeps the denormalised price range used for sorting/filtering in sync with active variants.
     */
    public function refreshPriceRange(): void
    {
        $variants = $this->variants()->where('is_active', true)->get(['price', 'compare_at_price']);
        $this->forceFill([
            'min_price' => (int) ($variants->min('price') ?? 0),
            'max_price' => (int) ($variants->max('price') ?? 0),
            'compare_at_price' => $variants->max('compare_at_price'),
        ])->save();
    }
}
