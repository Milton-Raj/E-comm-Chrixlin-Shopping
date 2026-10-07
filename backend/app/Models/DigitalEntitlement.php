<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $order_item_id
 * @property int $order_id
 * @property int|null $user_id
 * @property string $email
 * @property int|null $product_id
 * @property int|null $download_limit
 * @property int $downloads_used
 * @property Carbon|null $expires_at
 * @property string $status
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property-read Product|null $product
 * @property-read Order $order
 * @property-read OrderItem $orderItem
 */
class DigitalEntitlement extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['download_limit' => 'integer', 'downloads_used' => 'integer', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return HasMany<DigitalDownload, $this> */
    public function downloads(): HasMany
    {
        return $this->hasMany(DigitalDownload::class, 'entitlement_id');
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['available', 'downloaded'], true)
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && ($this->download_limit === null || $this->downloads_used < $this->download_limit);
    }
}
