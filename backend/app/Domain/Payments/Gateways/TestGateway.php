<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\GatewayEvent;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Development gateway (never enabled in production). The server itself plays the
 * provider: a confirmation produces the same GatewayEvent a real webhook would,
 * and it flows through the same idempotent processing.
 */
class TestGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'test';
    }

    public function createPayment(Payment $payment): array
    {
        $providerOrderId = 'test_order_'.Str::lower(Str::random(14));

        return [$providerOrderId, ['mode' => 'test', 'provider_order_id' => $providerOrderId, 'amount' => $payment->amount, 'currency' => $payment->currency]];
    }

    public function confirm(Payment $payment, array $data): ?GatewayEvent
    {
        $outcome = $data['outcome'] ?? null;
        if (! in_array($outcome, ['success', 'failure'], true)) {
            return null;
        }

        return new GatewayEvent(
            eventId: "test_evt_{$payment->uuid}_{$outcome}",
            type: $outcome === 'success' ? GatewayEvent::CAPTURED : GatewayEvent::FAILED,
            providerOrderId: $payment->provider_order_id,
            providerPaymentId: 'test_pay_'.substr(hash('sha256', $payment->uuid), 0, 14),
            amount: $payment->amount,
            currency: $payment->currency,
            method: 'test',
            failureMessage: $outcome === 'failure' ? 'The test payment was declined.' : null,
        );
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('payments.test.webhook_secret');
        $signature = (string) $request->header('X-Test-Signature');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function parseWebhook(Request $request): ?GatewayEvent
    {
        $data = $request->json()->all();
        if (! isset($data['id'], $data['type'])) {
            return null;
        }

        return new GatewayEvent(
            eventId: (string) $data['id'],
            type: $data['type'] === 'payment.captured' ? GatewayEvent::CAPTURED : GatewayEvent::FAILED,
            providerOrderId: $data['order_id'] ?? null,
            providerPaymentId: $data['payment_id'] ?? null,
            amount: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: $data['currency'] ?? null,
            method: 'test',
            payload: $data,
        );
    }

    public function refund(Payment $payment, int $amount, string $reason): string
    {
        return 'test_rfnd_'.Str::lower(Str::random(14));
    }
}
