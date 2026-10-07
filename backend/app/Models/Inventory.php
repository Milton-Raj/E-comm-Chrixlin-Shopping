<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of stock for one variant; always equal to the ledger sum (ARCHITECTURE §6.8).
 *
 * @property int $id
 * @property int $variant_id
 * @property int $on_hand
 * @property int $reserved
 * @property int $low_stock_threshold
 * @property-read ProductVariant $variant
 */
class Inventory extends Model
{
    protected $table = 'inventory';

    public const CREATED_AT = null;

    protected $fillable = ['variant_id', 'on_hand', 'reserved', 'low_stock_threshold'];

    protected function casts(): array
    {
        return ['on_hand' => 'integer', 'reserved' => 'integer', 'low_stock_threshold' => 'integer'];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function available(): int
    {
        return max(0, $this->on_hand - $this->reserved);
    }
}
