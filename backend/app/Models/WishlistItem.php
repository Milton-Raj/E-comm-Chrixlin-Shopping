<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $product_id
 * @property int $price_at_add
 * @property-read Product|null $product
 */
class WishlistItem extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'product_id', 'price_at_add'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
