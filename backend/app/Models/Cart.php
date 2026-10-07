<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $token
 * @property int|null $user_id
 * @property string $status
 * @property string $currency
 * @property string|null $email
 * @property string|null $phone
 * @property array<string, string|null>|null $shipping_address
 * @property string|null $shipping_method_code
 * @property string|null $coupon_code
 * @property-read Collection<int, CartItem> $items
 * @property-read User|null $user
 */
class Cart extends Model
{
    use HasPublicUuid;

    protected $fillable = ['token', 'user_id', 'status', 'currency', 'email', 'phone', 'shipping_address', 'shipping_method_code', 'coupon_code', 'last_activity_at'];

    protected function casts(): array
    {
        return ['shipping_address' => 'array', 'last_activity_at' => 'datetime'];
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
