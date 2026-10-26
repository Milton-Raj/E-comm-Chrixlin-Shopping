<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\GatewayManager;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Asks the provider about payments that never reached a final state (ARCHITECTURE §6.11, R7):
 * a webhook lost on shared hosting or a customer who closed the tab after paying still ends
 * with a paid order. Results flow through the same idempotent ProcessGatewayEvent.
 */
class ReconcilePayments
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly ProcessGatewayEvent $process,
    ) {}

    /** Scheduled sweep: attempts older than a few minutes (give the webhook a chance) and younger than two days. */
    public function __invoke(): int
    {
        $count = 0;
        $this->open()
            ->where('created_at', '<', now()->subMinutes(10))
            ->where('created_at', '>', now()->subDays(2))
            ->each(function (Payment $payment) use (&$count) {
                $count += $this->reconcile($payment) ? 1 : 0;
            });

        return $count;
    }

    /** Checks every open attempt of one order, e.g. right before it is expired as unpaid. */
    public function forOrder(Order $order): void
    {
        $this->open()->where('order_id', $order->id)->each(fn (Payment $payment) => $this->reconcile($payment));
    }

    /** @return Builder<Payment> */
    private function open(): Builder
    {
        // Cancelled attempts are included: a customer can still pay an older Razorpay window.
        return Payment::query()
            ->whereIn('status', [PaymentStatus::Initiated, PaymentStatus::Pending, PaymentStatus::Authorized, PaymentStatus::Cancelled, PaymentStatus::Failed])
            ->whereNotNull('provider_order_id')
            ->whereDoesntHave('order', fn ($q) => $q->where('payment_status', PaymentStatus::Captured));
    }

    private function reconcile(Payment $payment): bool
    {
        try {
            $event = $this->gateways->get($payment->provider)->fetchStatus($payment);
        } catch (ApiException) {
            return false; // gateway no longer configured
        }

        if (! $event) {
            return false;
        }

        $status = $this->process->handle($payment->provider, $event);
        if ($status === 'processed') {
            Log::channel('payments')->info('Payment reconciled with the provider.', ['payment' => $payment->uuid, 'type' => $event->type]);
        }

        return $status === 'processed';
    }
}
