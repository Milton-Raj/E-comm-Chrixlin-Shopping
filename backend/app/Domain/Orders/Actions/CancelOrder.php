<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Inventory\InventoryService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an unpaid order and releases its reserved stock. Paid orders are refunded instead.
 */
class CancelOrder
{
    public function __construct(private readonly InventoryService $inventory, private readonly OrderStateMachine $states) {}

    public function handle(Order $order, string $actorType, ?int $actorId, string $reason): void
    {
        DB::transaction(function () use ($order, $actorType, $actorId, $reason) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->payment_status === PaymentStatus::Captured) {
                throw new ApiException('Paid orders must be refunded rather than cancelled.', 409, 'order_paid');
            }
            if (! $order->status->isOpenForPayment()) {
                throw new ApiException('This order can no longer be cancelled.', 409, 'invalid_transition');
            }

            $this->inventory->release($order);
            $order->payments()->whereIn('status', [PaymentStatus::Initiated, PaymentStatus::Pending])->update(['status' => PaymentStatus::Cancelled]);
            $order->forceFill(['payment_status' => PaymentStatus::Cancelled])->save();
            $this->states->transition($order, OrderStatus::Cancelled, $actorType, $actorId, $reason);
        });
    }
}
