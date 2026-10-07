<?php

namespace App\Domain\Shipping\Actions;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Shipping\Courier\CourierStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies one Shiprocket tracking webhook exactly once: updates the shipment and moves
 * the order forward (Shipped → Out for delivery → Delivered). Never moves an order
 * backwards; exceptions (RTO, lost, undelivered) flag the order for staff attention.
 */
class ApplyCourierUpdate
{
    public function __construct(private readonly OrderStateMachine $states) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return string processed | duplicate | ignored | failed
     */
    public function handle(array $payload): string
    {
        $awb = trim((string) ($payload['awb'] ?? ''));
        $raw = (string) ($payload['current_status'] ?? $payload['shipment_status'] ?? '');
        if ($awb === '' || $raw === '') {
            return 'ignored';
        }

        $eventId = sha1(implode('|', [$awb, CourierStatus::normalise($raw), (string) ($payload['current_timestamp'] ?? '')]));
        try {
            $record = WebhookEvent::create([
                'provider' => BookCourierPickup::PROVIDER, 'event_id' => $eventId, 'event_type' => substr(CourierStatus::normalise($raw), 0, 64),
                'payload' => array_intersect_key($payload, array_flip(['awb', 'order_id', 'current_status', 'shipment_status', 'current_timestamp', 'courier_name', 'etd'])),
                'status' => 'received', 'attempts' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            return 'duplicate';
        }

        try {
            $status = DB::transaction(fn () => $this->apply($awb, (string) ($payload['order_id'] ?? ''), $raw, $payload));
            $record->forceFill(['status' => $status, 'processed_at' => now()])->save();

            return $status;
        } catch (Throwable $e) {
            $record->forceFill(['status' => 'failed', 'last_error' => $e->getMessage()])->save();
            Log::channel('shipping')->error('Courier update failed.', ['awb' => $awb, 'status' => $raw, 'error' => $e->getMessage()]);
            report($e);

            return 'failed';
        }
    }

    /** @param array<string, mixed> $payload */
    private function apply(string $awb, string $orderNumber, string $raw, array $payload): string
    {
        $shipment = Shipment::query()->where('provider', BookCourierPickup::PROVIDER)->where('tracking_number', $awb)->lockForUpdate()->first();
        if (! $shipment && $orderNumber !== '') {
            $shipment = Shipment::query()->where('provider', BookCourierPickup::PROVIDER)
                ->whereHas('order', fn ($q) => $q->where('order_number', $orderNumber))->latest('id')->lockForUpdate()->first();
        }
        if (! $shipment) {
            Log::channel('shipping')->info('Courier update for an unknown shipment ignored.', ['awb' => $awb]);

            return 'ignored';
        }

        $order = Order::query()->with('items')->lockForUpdate()->findOrFail($shipment->order_id);
        $label = CourierStatus::normalise($raw);
        $target = CourierStatus::orderStatus($raw);

        $shipment->forceFill([
            'courier_status' => substr($label, 0, 64),
            'last_event_at' => now(),
            'tracking_number' => $shipment->tracking_number ?: $awb,
            'carrier' => $shipment->carrier ?: (is_string($payload['courier_name'] ?? null) ? $payload['courier_name'] : null),
            'status' => match (true) {
                CourierStatus::isException($raw) => 'exception',
                $target !== null => 'in_transit',
                default => $shipment->status,
            },
            'shipped_at' => $shipment->shipped_at ?? ($target !== null ? now() : null),
        ])->save();

        if (CourierStatus::isException($raw)) {
            $order->forceFill(['requires_attention' => true])->save();
            $this->states->record($order, 'courier', null, "Courier reported: {$label}");

            return 'processed';
        }

        if ($target === null) {
            return 'processed';
        }

        $path = OrderStatus::fulfilmentPath();
        $current = array_search($order->status, $path, true);
        $wanted = array_search($target, $path, true);
        if ($current === false || $wanted <= $current) {
            return 'processed'; // late or out-of-order event: never move an order backwards
        }

        $this->states->advance($order, $target, 'courier', null, "{$shipment->carrier}: {$label}");

        return 'processed';
    }
}
