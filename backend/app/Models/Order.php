<?php

namespace App\Models;

use App\Domain\Localization\Presentment;
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
 * @property string|null $presentment_currency currency the customer was charged in (null = $currency)
 * @property string|null $exchange_rate locked rate, markup included (decimal string)
 * @property int|null $presentment_total amount charged, in $presentment_currency
 * @property string|null $market_rate market rate behind $exchange_rate (no markup)
 * @property int|null $exchange_markup_bps markup applied at placement
 * @property Carbon|null $exchange_rate_fetched_at when the market rate was fetched
 * @property int $subtotal
 * @property int $discount_total
 * @property int $shipping_total
 * @property int $gift_wrap_total
 * @property int $gift_wrap_tax
 * @property string|null $gift_message
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
 * @property Carbon|null $review_requested_at
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
 * @property-read Invoice|null $invoice
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
            'gift_wrap_total' => 'integer',
            'gift_wrap_tax' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'presentment_total' => 'integer',
            'exchange_markup_bps' => 'integer',
            'exchange_rate_fetched_at' => 'datetime',
            'refunded_total' => 'integer',
            'prices_include_tax' => 'boolean',
            'tax_breakdown' => 'array',
            'shipping_method' => 'array',
            'requires_attention' => 'boolean',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'review_requested_at' => 'datetime',
        ];
    }

    /** The locked conversion when the customer paid in another currency; null otherwise. */
    public function presentment(): ?Presentment
    {
        return $this->presentment_currency && $this->exchange_rate
            ? new Presentment($this->currency, $this->presentment_currency, $this->exchange_rate)
            : null;
    }

    /** What the gateway charges: the converted total abroad, the grand total at home. */
    public function chargeAmount(): int
    {
        return $this->presentment_total ?? $this->grand_total;
    }

    public function chargeCurrency(): string
    {
        return $this->presentment_currency ?? $this->currency;
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

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
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
