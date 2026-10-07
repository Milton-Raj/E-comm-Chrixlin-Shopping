<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $cart_id
 * @property int $variant_id
 * @property int $quantity
 * @property int|null $unit_price_seen
 * @property-read ProductVariant $variant
 */
class CartItem extends Model
{
    use HasPublicUuid;

    protected $fillable = ['cart_id', 'variant_id', 'quantity', 'unit_price_seen'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_seen' => 'integer'];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }
}
