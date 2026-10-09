<?php

namespace App\Domain\Shipping\Actions;

use App\Domain\Shipping\Courier\ShiprocketClient;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Log;

/**
 * Books a packed order with Shiprocket: creates the Shiprocket order, assigns a courier
 * (AWB) and requests pickup. Each step is saved as it succeeds, so a retry resumes where
 * the last attempt stopped instead of creating duplicates. Failures never undo "Packed";
 * they are stored on the shipment for staff to retry.
 */
class BookCourierPickup
{
    public const PROVIDER = 'shiprocket';

    public function __construct(private readonly ShiprocketClient $client) {}

    public function enabled(): bool
    {
        return $this->client->configured();
    }

    public function handle(Order $order, ?User $actor = null): Shipment
    {
        $order->loadMissing(['items.variant', 'addresses']);
        if (! $order->requiresShipping()) {
            throw new ApiException('This order has nothing to ship.', 409, 'not_shippable');
        }

        $shipment = $order->shipments()->where('provider', self::PROVIDER)->whereNull('delivered_at')->latest('id')->first()
            ?? $order->shipments()->create(['provider' => self::PROVIDER, 'status' => 'booking']);

        if ($shipment->status === 'pickup_scheduled' || $shipment->shipped_at !== null) {
            return $shipment;
        }
        $testMode = (bool) config('shipping.shiprocket.test_mode');
        if ($testMode && $shipment->status === 'test_created') {
            return $shipment;
        }

        try {
            if (! $shipment->provider_shipment_id) {
                $created = $this->client->post('/orders/create/adhoc', $this->orderPayload($order));
                $shipmentId = (string) data_get($created, 'shipment_id', '');
                if ($shipmentId === '') {
                    throw new ApiException('Shiprocket: '.(string) data_get($created, 'message', 'the order was not created.'), 502, 'courier_error');
                }
                $shipment->forceFill(['provider_order_id' => (string) data_get($created, 'order_id'), 'provider_shipment_id' => $shipmentId])->save();
            }

            if ($testMode) {
                // Stop before anything that costs money or sends a courier.
                $shipment->forceFill(['status' => 'test_created', 'courier_status' => 'TEST ORDER CREATED', 'last_error' => null, 'last_event_at' => now()])->save();
                Audit::record('order.courier_test_created', $order, null, ['provider' => self::PROVIDER, 'shiprocket_order_id' => $shipment->provider_order_id], $actor);
                Log::channel('shipping')->info('Shiprocket test mode: order created, no courier booked.', ['order' => $order->order_number, 'shiprocket_order_id' => $shipment->provider_order_id]);

                return $shipment;
            }

            if (! $shipment->tracking_number) {
                $assigned = $this->client->post('/courier/assign/awb', ['shipment_id' => (int) $shipment->provider_shipment_id]);
                $awb = (string) data_get($assigned, 'response.data.awb_code', '');
                if ((int) data_get($assigned, 'awb_assign_status') !== 1 || $awb === '') {
                    $why = data_get($assigned, 'response.data.awb_assign_error') ?? data_get($assigned, 'message') ?? 'no courier could be assigned.';
                    throw new ApiException('Shiprocket: '.(string) $why, 502, 'courier_error');
                }
                $shipment->forceFill([
                    'tracking_number' => $awb,
                    'carrier' => (string) data_get($assigned, 'response.data.courier_name', 'Shiprocket'),
                    'tracking_url' => str_replace('{awb}', rawurlencode($awb), (string) config('shipping.shiprocket.tracking_url')),
                ])->save();
            }

            $pickup = $this->client->post('/courier/generate/pickup', ['shipment_id' => [(int) $shipment->provider_shipment_id]]);
            if ((int) data_get($pickup, 'pickup_status') !== 1) {
                throw new ApiException('Shiprocket: '.(string) (data_get($pickup, 'message') ?? 'the pickup could not be scheduled.'), 502, 'courier_error');
            }
            $date = data_get($pickup, 'response.pickup_scheduled_date');

            $shipment->forceFill([
                'status' => 'pickup_scheduled', 'last_error' => null, 'courier_status' => 'PICKUP SCHEDULED',
                'pickup_scheduled_at' => is_string($date) && $date !== '' ? $date : null,
            ])->save();
            Audit::record('order.courier_booked', $order, null, ['provider' => self::PROVIDER, 'awb' => $shipment->tracking_number, 'courier' => $shipment->carrier], $actor);
            Log::channel('shipping')->info('Courier pickup booked.', ['order' => $order->order_number, 'awb' => $shipment->tracking_number]);
        } catch (ApiException $e) {
            $shipment->forceFill(['status' => 'failed', 'last_error' => $e->getMessage()])->save();
            Audit::record('order.courier_booking_failed', $order, null, ['provider' => self::PROVIDER, 'error' => $e->getMessage()], $actor);
            Log::channel('shipping')->warning('Courier booking failed.', ['order' => $order->order_number, 'error' => $e->getMessage()]);
        }

        return $shipment->refresh();
    }

    /** @return array<string, mixed> */
    private function orderPayload(Order $order): array
    {
        $address = $order->shippingAddress() ?? throw new ApiException('This order has no shipping address.', 409, 'no_address');
        $exp = Money::exponent($order->currency);
        $major = fn (int $minor) => round($minor / (10 ** $exp), $exp);
        $items = $order->items->filter(fn (OrderItem $i) => $i->requires_shipping);
        $nameParts = preg_split('/\s+/', trim($address->name), 2) ?: [$address->name];
        $grams = (int) $items->sum(fn (OrderItem $i) => ($i->variant->weight_grams ?? 0) * $i->quantity);
        $package = (array) config('shipping.shiprocket.package');
        $phone = substr((string) preg_replace('/\D/', '', (string) ($address->phone ?: $order->phone)), -10);

        return [
            'order_id' => $order->order_number,
            'order_date' => ($order->placed_at ?? $order->created_at)->format('Y-m-d H:i'),
            'pickup_location' => (string) config('shipping.shiprocket.pickup_location'),
            'billing_customer_name' => $nameParts[0],
            'billing_last_name' => $nameParts[1] ?? '',
            'billing_address' => $address->line1,
            'billing_address_2' => (string) $address->line2,
            'billing_city' => $address->city,
            'billing_pincode' => $address->postal_code,
            'billing_state' => config("regions.states.{$address->country_code}.{$address->state_code}", $address->state_code),
            'billing_country' => config("regions.countries.{$address->country_code}", $address->country_code),
            'billing_email' => $order->email,
            'billing_phone' => $phone,
            'shipping_is_billing' => true,
            'order_items' => $items->map(fn (OrderItem $i) => [
                'name' => $i->variant_name ? "{$i->name} — {$i->variant_name}" : $i->name,
                'sku' => $i->sku,
                'units' => $i->quantity,
                'selling_price' => $major($i->unit_price),
                'discount' => $major(intdiv($i->discount_total, max(1, $i->quantity))),
            ])->values()->all(),
            // Online payments only: the courier never collects cash.
            'payment_method' => 'Prepaid',
            'shipping_charges' => $major($order->shipping_total),
            'total_discount' => 0,
            'sub_total' => $major((int) $items->sum('line_total')),
            'length' => $package['length_cm'],
            'breadth' => $package['breadth_cm'],
            'height' => $package['height_cm'],
            'weight' => round(max($grams, (int) $package['default_weight_grams']) / 1000, 3),
        ];
    }
}
