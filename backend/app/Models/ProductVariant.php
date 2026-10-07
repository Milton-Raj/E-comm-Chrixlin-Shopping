<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property int $product_id
 * @property string $sku
 * @property string|null $name
 * @property array<string, string>|null $options
 * @property int $price
 * @property int|null $compare_at_price
 * @property int|null $cost_price
 * @property bool $track_inventory
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Product $product
 * @property-read Inventory|null $inventory
 */
class ProductVariant extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = ['product_id', 'sku', 'name', 'options', 'price', 'compare_at_price', 'cost_price', 'weight_grams', 'track_inventory', 'is_default', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'cost_price' => 'integer',
            'track_inventory' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasOne<Inventory, $this> */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'variant_id');
    }

    public function available(): ?int
    {
        if (! $this->track_inventory) {
            return null; // unlimited (e.g. digital)
        }

        return max(0, ($this->inventory->on_hand ?? 0) - ($this->inventory->reserved ?? 0));
    }
}
