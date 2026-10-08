<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Digital\EntitlementService;
use App\Domain\Inventory\InventoryService;
use App\Domain\Invoices\IssueInvoice;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\OrderStateMachine;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\GatewayEvent;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\WebhookEvent;
use App\Notifications\OrderPaidNotification;
use App\Support\Logging\RedactSensitiveData;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Applies a verified gateway event exactly once (PRD §30). Used by webhooks and by
 * server-verified browser confirmations, so both paths share one idempotent code path.
 */
class ProcessGatewayEvent
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly OrderStateMachine $states,
        private readonly EntitlementService $entitlements,
        private readonly IssueInvoice $invoices,
    ) {}

    /** @return string status: processed | duplicate | ignored | failed */
    public function handle(string $provider, GatewayEvent $event): string
    {
        try {
            $record = WebhookEvent::create([
                'provider' => $provider, 'event_id' => $event->eventId, 'event_type' => $event->type,
                'payload' => RedactSensitiveData::redact($event->payload), 'status' => 'received', 'attempts' => 1,
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::channel('webhooks')->info('Duplicate payment event ignored.', ['provider' => $provider, 'event' => $event->eventId]);

            return 'duplicate';
        }

        try {
            $status = DB::transaction(fn () => $this->apply($provider, $event));
            $record->forceFill(['status' => $status, 'processed_at' => now()])->save();

            return $status;
        } catch (Throwable $e) {
            $record->forceFill(['status' => 'failed', 'last_error' => $e->getMessage()])->save();
            Log::channel('webhooks')->error('Payment event processing failed.', ['provider' => $provider, 'event' => $event->eventId, 'error' => $e->getMessage()]);
            report($e);

            return 'failed';
        }
    }

    private function apply(string $provider, GatewayEvent $event): string
    {
        $payment = Payment::query()->where('provider', $provider)
            ->where(fn ($q) => $q->where('provider_order_id', $event->providerOrderId)->when($event->providerPaymentId, fn ($q) => $q->orWhere('provider_payment_id', $event->providerPaymentId)))
            ->lockForUpdate()->latest('id')->first();

        if (! $payment) {
            return 'ignored';
        }

        $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();

        return match ($event->type) {
            GatewayEvent::CAPTURED => $this->captured($payment, $order, $event),
            GatewayEvent::FAILED => $this->failed($payment, $order, $event),
            default => 'ignored',
        };
    }

    private function captured(Payment $payment, Order $order, GatewayEvent $event): string
    {
        if ($payment->status === PaymentStatus::Captured) {
            return 'processed'; // already applied via the other path
        }

        if ($event->amount !== $payment->amount || ($event->currency && strtoupper($event->currency) !== $payment->currency)) {
            $order->forceFill(['requires_attention' => true])->save();
            Log::channel('payments')->critical('Captured amount does not match the order.', ['order' => $order->order_number, 'expected' => $payment->amount, 'got' => $event->amount]);

            return 'failed';
        }

        $payment->forceFill([
            'status' => PaymentStatus::Captured, 'provider_payment_id' => $event->providerPaymentId,
            'method' => $event->method, 'captured_at' => now(),
        ])->save();
        $payment->transactions()->create([
            'type' => 'capture', 'amount' => $payment->amount, 'currency' => $payment->currency,
            'status' => 'succeeded', 'provider_transaction_id' => $event->providerPaymentId, 'payload' => RedactSensitiveData::redact($event->payload),
        ]);

        $order->forceFill(['payment_status' => PaymentStatus::Captured, 'paid_at' => now()])->save();
        if ($order->status === OrderStatus::Cancelled) {
            // Paid after the order was cancelled (e.g. expired): keep the money traceable for a manual refund.
            $order->forceFill(['requires_attention' => true])->save();

            return 'processed';
        }
        $this->states->transition($order, OrderStatus::Paid, 'gateway', null, 'Payment captured');

        if (! $this->inventory->commit($order)) {
            $order->forceFill(['requires_attention' => true])->save();
        }

        $order->load('items');
        foreach ($order->items as $item) {
            if ($item->product_id) {
                Product::query()->whereKey($item->product_id)->increment('units_sold', $item->quantity);
            }
        }

        if ($order->coupon_code && ($coupon = Coupon::query()->where('code', $order->coupon_code)->lockForUpdate()->first())) {
            $coupon->increment('usage_count');
            DB::table('coupon_usages')->insertOrIgnore([
                'coupon_id' => $coupon->id, 'order_id' => $order->id, 'user_id' => $order->user_id,
                'email' => $order->email, 'discount_amount' => $order->discount_total, 'created_at' => now(),
            ]);
        }

        $this->entitlements->grantForOrder($order);
        $this->invoices->handle($order); // GST tax invoice, numbered once; emailed with the confirmation
        if (! $order->requiresShipping()) {
            $this->states->transition($order, OrderStatus::Delivered, 'system', null, 'Digital items delivered');
        } else {
            $this->states->transition($order, OrderStatus::Processing, 'system', null, 'Awaiting fulfilment');
        }

        if ($order->cart_id) {
            Cart::query()->whereKey($order->cart_id)->update(['status' => 'converted']);
        }

        DB::afterCommit(function () use ($order) {
            Notification::route('mail', $order->email)->notify(new OrderPaidNotification($order->fresh(['items', 'entitlements', 'invoice']) ?? $order));
        });

        Log::channel('payments')->info('Payment captured.', ['order' => $order->order_number, 'amount' => $payment->amount]);

        return 'processed';
    }

    private function failed(Payment $payment, Order $order, GatewayEvent $event): string
    {
        if (in_array($payment->status, [PaymentStatus::Captured, PaymentStatus::Failed], true)) {
            return 'processed';
        }

        $payment->forceFill(['status' => PaymentStatus::Failed, 'failure_message' => $event->failureMessage])->save();
        $payment->transactions()->create([
            'type' => 'failure', 'amount' => $payment->amount, 'currency' => $payment->currency,
            'status' => 'failed', 'provider_transaction_id' => $event->providerPaymentId ?? $event->eventId,
        ]);
        $order->forceFill(['payment_status' => PaymentStatus::Failed])->save();

        Log::channel('payments')->notice('Payment failed.', ['order' => $order->order_number]);

        return 'processed';
    }
}
