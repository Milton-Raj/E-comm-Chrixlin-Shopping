<?php

namespace App\Http\Resources;

use App\Domain\Shipping\Actions\BookCourierPickup;
use App\Models\DigitalEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
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
        $money = fn (int $amount) => Money::of($amount, $this->currency)->toArray();
        $address = $this->relationLoaded('addresses') ? $this->shippingAddress() : null;

        $data = [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'payment_status' => $this->payment_status->value,
            'fulfillment_status' => $this->fulfillment_status->value,
            'email' => $this->email,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'currency' => $this->currency,
            'totals' => [
                'subtotal' => $money($this->subtotal),
                'discount' => $money($this->discount_total),
                'shipping' => $money($this->shipping_total),
                'tax' => $money($this->tax_total),
                'total' => $money($this->grand_total),
                'refunded' => $money($this->refunded_total),
            ],
            'item_count' => $this->relationLoaded('items') ? $this->items->sum('quantity') : null,
        ];

        if (! $this->relationLoaded('items')) {
            return $data;
        }

        $data += [
            'phone' => $this->phone,
            'tax_breakdown' => collect($this->tax_breakdown ?? [])->map(fn (int $amount, string $code) => ['code' => $code, 'amount' => $money($amount)])->values(),
            'coupon_code' => $this->coupon_code,
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
                'customer' => $this->user ? ['uuid' => $this->user->uuid, 'name' => $this->user->name, 'email' => $this->user->email] : null,
                'requires_attention' => $this->requires_attention,
                'payments' => $this->payments->map(fn ($p) => [
                    'uuid' => $p->uuid, 'provider' => $p->provider, 'status' => $p->status->value, 'amount' => $money($p->amount),
                    'method' => $p->method, 'provider_payment_id' => $p->provider_payment_id, 'created_at' => $p->created_at->toIso8601String(),
                ])->values(),
                'refunds' => $this->refunds->map(fn ($r) => ['uuid' => $r->uuid, 'amount' => $money($r->amount), 'reason' => $r->reason, 'status' => $r->status, 'created_at' => $r->created_at->toIso8601String()])->values(),
                'allowed_transitions' => array_map(fn ($s) => $s->value, $this->status->allowedTransitions()),
                'courier_enabled' => app(BookCourierPickup::class)->enabled(),
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
