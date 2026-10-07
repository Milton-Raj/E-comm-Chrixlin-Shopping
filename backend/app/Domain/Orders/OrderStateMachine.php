<?php

namespace App\Domain\Orders;

use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\OrderStatusHistory;

/**
 * The single place order status changes (ARCHITECTURE §6.10). Every transition is
 * validated and written to order_status_history.
 */
class OrderStateMachine
{
    public function transition(Order $order, OrderStatus $to, string $actorType = 'system', ?int $actorId = null, ?string $reason = null): void
    {
        $from = $order->status;
        if ($from === $to) {
            return;
        }

        if (! $from->canTransitionTo($to)) {
            throw new ApiException("An order cannot move from {$from->label()} to {$to->label()}.", 409, 'invalid_transition');
        }

        $order->status = $to;
        if ($to === OrderStatus::Cancelled) {
            $order->cancelled_at = now();
        }
        $order->save();

        OrderStatusHistory::create([
            'order_id' => $order->id, 'from_status' => $from->value, 'to_status' => $to->value,
            'actor_type' => $actorType, 'actor_id' => $actorId, 'reason' => $reason,
        ]);
    }

    /**
     * Moves a shipped-goods order forward along the fulfilment path, recording every
     * intermediate stage (e.g. Processing → Packed → Shipped). Staff cannot skip past
     * "Shipped": that stage needs a shipment (manual tracking or a courier booking).
     * Orders without physical items, or targets off the path, use a direct transition.
     */
    public function advance(Order $order, OrderStatus $to, string $actorType = 'system', ?int $actorId = null, ?string $reason = null): void
    {
        $path = OrderStatus::fulfilmentPath();
        $from = array_search($order->status, $path, true);
        $target = array_search($to, $path, true);

        if (! $order->requiresShipping() || $from === false || $target === false || $target <= $from) {
            $this->transition($order, $to, $actorType, $actorId, $reason);
        } else {
            for ($i = $from + 1; $i <= $target; $i++) {
                if ($path[$i] === OrderStatus::Shipped && $actorType === 'staff') {
                    throw new ApiException('Book the courier or add tracking details before moving this order past "Shipped".', 409, 'shipment_required');
                }
                $this->transition($order, $path[$i], $actorType, $actorId, $i === $target ? $reason : null);
            }
        }

        if ($to === OrderStatus::Delivered) {
            $order->forceFill(['fulfillment_status' => $order->requiresShipping() ? FulfillmentStatus::Fulfilled : FulfillmentStatus::NotRequired])->save();
            $order->shipments()->whereNull('delivered_at')->update(['delivered_at' => now(), 'status' => 'delivered']);
        }
    }

    public function record(Order $order, string $actorType = 'system', ?int $actorId = null, ?string $reason = null): void
    {
        OrderStatusHistory::create([
            'order_id' => $order->id, 'from_status' => null, 'to_status' => $order->status->value,
            'actor_type' => $actorType, 'actor_id' => $actorId, 'reason' => $reason,
        ]);
    }
}
