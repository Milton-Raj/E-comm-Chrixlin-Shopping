<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Shipping\Actions\BookCourierPickup;
use App\Models\DigitalEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Review;
use App\Models\Shipment;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public bool $forAdmin = false;

    public function forAdmin(): static
    {
        $this->forAdmin = true;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Customers see the currency they paid in (the locked rate reproduces their bag's figures);
        // staff see the store currency, with the charged amount beside it.
        $presentment = $this->forAdmin || $request->routeIs('v1.admin.*') ? null : $this->presentment();
        $money = fn (int $amount) => ($presentment ? $presentment->money($amount) : Money::of($amount, $this->currency))->toArray();
        $subtotal = $presentment && $this->relationLoaded('items')
            ? Money::of($this->items->sum(fn (OrderItem $i) => $presentment->convert($i->unit_price) * $i->quantity), $presentment->currency)->toArray()
            : $money($this->subtotal);
        $refunded = $presentment && $this->relationLoaded('refunds')
            ? Money::of((int) $this->refunds->sum(fn ($r) => $r->gateway_amount ?? $presentment->convert($r->amount)), $presentment->currency)->toArray()
            : $money($this->refunded_total);
        $address = $this->relationLoaded('addresses') ? $this->shippingAddress() : null;

        $data = [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status->value,
            'fulfillment_status' => $this->fulfillment_status->value,
            'email' => $this->email,
            'requires_shipping' => $this->relationLoaded('items') ? $this->requiresShipping() : null,
            'invoice_number' => $this->relationLoaded('invoice') ? $this->invoice?->invoice_number : null,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'currency' => $presentment->currency ?? $this->currency,
            'charged' => $this->presentment_currency ? [
                'amount' => Money::of($this->chargeAmount(), $this->chargeCurrency())->toArray(),
                'exchange_rate' => rtrim(rtrim((string) $this->exchange_rate, '0'), '.'),
                'base_currency' => $this->currency,
            ] : null,
            'totals' => [
                'subtotal' => $subtotal,
                'discount' => $money($this->discount_total),
                'shipping' => $money($this->shipping_total),
                'gift_wrap' => $money($this->gift_wrap_total),
                'tax' => $money($this->tax_total),
                'total' => $presentment ? Money::of($this->chargeAmount(), $this->chargeCurrency())->toArray() : $money($this->grand_total),
                'refunded' => $refunded,
            ],
            'item_count' => $this->relationLoaded('items') ? $this->items->sum('quantity') : null,
        ];

        if (! $this->relationLoaded('items')) {
            return $data;
        }

        // Delivered orders invite a review of each piece the customer has not reviewed yet.
        $canReview = ! $this->forAdmin && $this->status === OrderStatus::Delivered;
        $reviewed = $canReview && $this->user_id
            ? Review::withTrashed()->where('user_id', $this->user_id)->whereIn('product_id', $this->items->pluck('product_id')->filter())->pluck('product_id')->all()
            : [];

        $data += [
            'phone' => $this->phone,
            'tax_breakdown' => collect($this->tax_breakdown ?? [])->map(fn (int $amount, string $code) => ['code' => $code, 'amount' => $money($amount)])->values(),
            'coupon_code' => $this->coupon_code,
            // Shown to the customer and to staff packing the order.
            'gift' => $this->gift_wrap_total > 0 || $this->gift_message !== null ? ['wrap' => true, 'message' => $this->gift_message] : null,
            'shipping_method' => $this->shipping_method,
            'shipping_address' => $address?->only(['name', 'phone', 'line1', 'line2', 'city', 'state_code', 'postal_code', 'country_code']),
            'items' => $this->items->map(fn (OrderItem $item) => [
                'uuid' => $item->uuid,
                'name' => $item->name,
                'variant_name' => $item->variant_name,
                'sku' => $item->sku,
                'product_type' => $item->product_type,
                'product_slug' => $item->product?->slug,
                'image' => $item->image_path ? Storage::disk('public')->url($item->image_path) : null,
                'unit_price' => $money($item->unit_price),
                'quantity' => $item->quantity,
                'line_total' => $money($item->line_total),
                'can_review' => $canReview && $item->product?->status === ProductStatus::Active && ! in_array($item->product_id, $reviewed, true),
            ])->values(),
            'shipments' => $this->relationLoaded('shipments') ? $this->shipments->filter(fn (Shipment $s) => $s->tracking_number !== null)->map(fn (Shipment $s) => $s->only(['carrier', 'tracking_number', 'tracking_url', 'courier_status']) + ['shipped_at' => $s->shipped_at?->toIso8601String()])->values() : [],
            'history' => $this->relationLoaded('history') ? $this->history->map(fn (OrderStatusHistory $h) => [
                'from' => $h->from_status, 'to' => $h->to_status, 'actor' => $h->actor_type, 'reason' => $h->reason, 'at' => $h->created_at->toIso8601String(),
            ])->values() : [],
            'downloads' => $this->relationLoaded('entitlements') ? $this->entitlements->map(fn (DigitalEntitlement $e) => EntitlementResource::make($e)->resolve($request))->values() : [],
            'can_pay' => $this->status->isOpenForPayment() && $this->payment_status->value !== 'captured',
            'can_cancel' => $this->status->isOpenForPayment() && $this->payment_status->value !== 'captured',
        ];

        if ($this->forAdmin) {
            $data += [
                'customer' => $this->user ? ['uuid' => $this->user->uuid, 'customer_number' => $this->user->customer_number, 'name' => $this->user->name, 'email' => $this->user->email] : null,
                'requires_attention' => $this->requires_attention,
                'payments' => $this->payments->map(fn ($p) => [
                    'uuid' => $p->uuid, 'provider' => $p->provider, 'status' => $p->status->value, 'amount' => Money::of($p->amount, $p->currency)->toArray(),
                    'method' => $p->method, 'provider_payment_id' => $p->provider_payment_id, 'created_at' => $p->created_at->toIso8601String(),
                ])->values(),
                'refunds' => $this->refunds->map(fn ($r) => ['uuid' => $r->uuid, 'amount' => $money($r->amount), 'gateway_amount' => $r->gateway_currency && $r->gateway_currency !== $r->currency ? Money::of((int) $r->gateway_amount, $r->gateway_currency)->toArray() : null, 'reason' => $r->reason, 'status' => $r->status, 'created_at' => $r->created_at->toIso8601String()])->values(),
                'allowed_transitions' => array_map(fn ($s) => $s->value, $this->status->allowedTransitions()),
                'courier_enabled' => app(BookCourierPickup::class)->enabled() && app(BookCourierPickup::class)->supports($this->resource),
                'courier_shipments' => $this->relationLoaded('shipments') ? $this->shipments->map(fn (Shipment $s) => [
                    'uuid' => $s->uuid, 'provider' => $s->provider, 'carrier' => $s->carrier, 'tracking_number' => $s->tracking_number, 'tracking_url' => $s->tracking_url,
                    'status' => $s->status, 'courier_status' => $s->courier_status, 'pickup_scheduled_at' => $s->pickup_scheduled_at?->toIso8601String(),
                    'last_error' => $s->last_error, 'last_event_at' => $s->last_event_at?->toIso8601String(),
                    'shipped_at' => $s->shipped_at?->toIso8601String(), 'delivered_at' => $s->delivered_at?->toIso8601String(),
                ])->values() : [],
                'ip_address' => $this->ip_address,
            ];
        }

        return $data;
    }
}
