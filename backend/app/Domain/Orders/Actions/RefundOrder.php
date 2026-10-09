<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Digital\EntitlementService;
use App\Domain\Inventory\InventoryService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\GatewayManager;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\OrderRefundedNotification;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Full or partial refund through the original gateway (ARCHITECTURE §6.11).
 * Full refunds revoke digital access and can restock physical items.
 */
class RefundOrder
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly InventoryService $inventory,
        private readonly OrderStateMachine $states,
        private readonly EntitlementService $entitlements,
    ) {}

    public function handle(Order $order, int $amount, string $reason, bool $restock, User $actor): Refund
    {
        $refund = DB::transaction(function () use ($order, $amount, $reason, $restock, $actor) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            /** @var Payment|null $payment */
            $payment = $order->payments()->whereIn('status', [PaymentStatus::Captured, PaymentStatus::PartiallyRefunded])->latest('id')->first();

            if (! $payment) {
                throw new ApiException('This order has no captured payment to refund.', 409, 'not_refundable');
            }
            $refundable = $order->grand_total - $order->refunded_total;
            if ($amount <= 0 || $amount > $refundable) {
                throw new ApiException('Refund amount must be between ₹0.01 and the remaining paid amount.', 422, 'invalid_amount', ['amount' => ["Maximum refundable is {$refundable}."]]);
            }

            $providerRefundId = $this->gateways->get($payment->provider)->refund($payment, $amount, $reason);
            $refund = $order->refunds()->create([
                'payment_id' => $payment->id, 'amount' => $amount, 'currency' => $order->currency, 'reason' => $reason,
                'status' => 'processed', 'provider_refund_id' => $providerRefundId, 'restock' => $restock, 'requested_by' => $actor->id,
            ]);
            $payment->transactions()->create([
                'type' => 'refund', 'amount' => $amount, 'currency' => $order->currency, 'status' => 'succeeded', 'provider_transaction_id' => $providerRefundId,
            ]);

            $before = $order->only(['status', 'refunded_total']);
            $fully = $order->refunded_total + $amount >= $order->grand_total;
            $order->forceFill([
                'refunded_total' => $order->refunded_total + $amount,
                'payment_status' => $fully ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
            ])->save();
            $payment->forceFill(['status' => $fully ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded])->save();
            $this->states->transition($order, $fully ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded, 'staff', $actor->id, $reason);

            if ($fully) {
                $this->entitlements->revokeForOrder($order, 'refunded', 'Order refunded');
                if ($restock) {
                    foreach ($order->items as $item) {
                        if ($item->requires_shipping && $item->variant_id && ($variant = ProductVariant::withTrashed()->find($item->variant_id))) {
                            $this->inventory->restock($variant, $item->quantity, 'return', $order, $actor);
                        }
                    }
                }
            }

            Audit::record('order.refunded', $order, $before, $order->only(['status', 'refunded_total']), $actor);

            return $refund;
        });

        // Tell the customer once the refund is committed (queued, so a mail hiccup never undoes it).
        $order->refresh();
        Notification::route('mail', $order->email)->notify(new OrderRefundedNotification($order, $amount, $order->refunded_total >= $order->grand_total));

        return $refund;
    }
}
