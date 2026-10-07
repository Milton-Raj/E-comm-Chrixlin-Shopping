<?php

namespace App\Models;

use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Immutable financial record: amounts are snapshots and are never edited (PRD §67).
 *
 * @property int $id
 * @property string $uuid
 * @property string $order_number
 * @property int|null $user_id
 * @property string $email
 * @property string|null $phone
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property FulfillmentStatus $fulfillment_status
 * @property string $currency
 * @property int $subtotal
 * @property int $discount_total
 * @property int $shipping_total
 * @property int $tax_total
 * @property int $grand_total
 * @property int $refunded_total
 * @property bool $prices_include_tax
 * @property array<string, int>|null $tax_breakdown
 * @property string|null $coupon_code
 * @property array<string, mixed>|null $shipping_method
 * @property bool $requires_attention
 * @property Carbon|null $placed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderAddress> $addresses
 * @property-read Collection<int, Payment> $payments
 * @property-read Collection<int, OrderStatusHistory> $history
 * @property-read Collection<int, Shipment> $shipments
 * @property-read Collection<int, DigitalEntitlement> $entitlements
 * @property-read Collection<int, Refund> $refunds
 * @property-read Payment|null $latestPayment
 * @property-read User|null $user
 */
class Order extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'refunded_total' => 'integer',
            'prices_include_tax' => 'boolean',
            'tax_breakdown' => 'array',
            'shipping_method' => 'array',
            'requires_attention' => 'boolean',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasOne<Payment, $this> */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasMany<DigitalEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(DigitalEntitlement::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requiresShipping(): bool
    {
        return $this->items->contains(fn (OrderItem $item) => $item->requires_shipping);
    }

    public function shippingAddress(): ?OrderAddress
    {
        return $this->addresses->firstWhere('type', 'shipping');
    }
}
