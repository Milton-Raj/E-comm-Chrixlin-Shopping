<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string $product_type
 * @property string $sku
 * @property string $name
 * @property string|null $variant_name
 * @property string|null $image_path
 * @property int $unit_price
 * @property int $quantity
 * @property int $line_subtotal
 * @property int $discount_total
 * @property int $tax_total
 * @property int $line_total
 * @property bool $requires_shipping
 * @property int $refunded_quantity
 * @property-read Order $order
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 * @property-read DigitalEntitlement|null $entitlement
 */
class OrderItem extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer', 'quantity' => 'integer', 'line_subtotal' => 'integer', 'discount_total' => 'integer',
            'tax_total' => 'integer', 'line_total' => 'integer', 'requires_shipping' => 'boolean', 'refunded_quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    /** @return HasOne<DigitalEntitlement, $this> */
    public function entitlement(): HasOne
    {
        return $this->hasOne(DigitalEntitlement::class);
    }
}
