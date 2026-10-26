<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Payments\GatewayEvent;
use App\Exceptions\ApiException;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay (India: UPI, cards, net banking). Enabled only when keys are configured.
 * Confirmation = signature check AND a server-to-server status fetch (PRD §29).
 */
class RazorpayGateway implements PaymentGateway
{
    private const API = 'https://api.razorpay.com/v1';

    public function key(): string
    {
        return 'razorpay';
    }

    public function createPayment(Payment $payment): array
    {
        $response = $this->http(retry: false)->post(self::API.'/orders', [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'receipt' => $payment->order->order_number,
            'notes' => ['order_number' => $payment->order->order_number],
        ]);

        if (! $response->successful()) {
            Log::channel('payments')->error('Razorpay order creation failed.', ['status' => $response->status(), 'order' => $payment->order->order_number]);
            throw new ApiException('We could not start the payment. Please try again.', 502, 'gateway_error');
        }

        $orderId = (string) $response->json('id');

        return [$orderId, [
            'mode' => 'razorpay',
            'key_id' => config('payments.razorpay.key_id'),
            'order_id' => $orderId,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'name' => config('commerce.store.name'),
            'description' => $payment->order->order_number,
            'prefill' => ['email' => $payment->order->email, 'contact' => $payment->order->phone],
        ]];
    }

    public function confirm(Payment $payment, array $data): ?GatewayEvent
    {
        $paymentId = (string) ($data['razorpay_payment_id'] ?? '');
        $orderId = (string) ($data['razorpay_order_id'] ?? '');
        $signature = (string) ($data['razorpay_signature'] ?? '');

        $expected = hash_hmac('sha256', "{$orderId}|{$paymentId}", (string) config('payments.razorpay.key_secret'));
        if ($paymentId === '' || $orderId !== $payment->provider_order_id || ! hash_equals($expected, $signature)) {
            Log::channel('payments')->warning('Razorpay signature verification failed.', ['payment' => $payment->uuid]);

            return null;
        }

        // Signature proves origin; the provider's own status is the source of truth.
        $remote = $this->http()->get(self::API."/payments/{$paymentId}");
        if (! $remote->successful()) {
            return null;
        }

        return $this->eventFromRemote($payment, (array) $remote->json());
    }

    public function fetchStatus(Payment $payment): ?GatewayEvent
    {
        if (! $payment->provider_order_id) {
            return null;
        }

        $remote = $this->http()->get(self::API."/orders/{$payment->provider_order_id}/payments");
        if (! $remote->successful()) {
            Log::channel('payments')->warning('Razorpay status fetch failed.', ['status' => $remote->status(), 'payment' => $payment->uuid]);

            return null;
        }

        // One Razorpay order can hold several attempts (a declined card, then UPI). Money wins.
        $attempts = collect((array) $remote->json('items', []));
        $best = $attempts->firstWhere('status', 'captured')
            ?? $attempts->firstWhere('status', 'authorized')
            ?? ($attempts->isNotEmpty() && $attempts->every(fn ($a) => ($a['status'] ?? null) === 'failed') ? $attempts->first() : null);

        return $best ? $this->eventFromRemote($payment, (array) $best) : null;
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) config('payments.razorpay.webhook_secret');
        $signature = (string) $request->header('X-Razorpay-Signature');

        return $secret !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }

    public function parseWebhook(Request $request): ?GatewayEvent
    {
        $event = (string) $request->json('event');
        $entity = (array) $request->json('payload.payment.entity', []);
        if (! in_array($event, ['payment.captured', 'payment.failed', 'order.paid'], true) || $entity === []) {
            return null;
        }

        return new GatewayEvent(
            eventId: (string) ($request->header('X-Razorpay-Event-Id') ?: $event.'_'.($entity['id'] ?? '')),
            type: $event === 'payment.failed' ? GatewayEvent::FAILED : GatewayEvent::CAPTURED,
            providerOrderId: $entity['order_id'] ?? null,
            providerPaymentId: $entity['id'] ?? null,
            amount: isset($entity['amount']) ? (int) $entity['amount'] : null,
            currency: $entity['currency'] ?? null,
            method: $entity['method'] ?? null,
            failureMessage: $entity['error_description'] ?? null,
            payload: ['event' => $event, 'payment_id' => $entity['id'] ?? null],
        );
    }

    public function refund(Payment $payment, int $amount, string $reason): string
    {
        // Never retried blindly, and keyed so a repeated request cannot refund twice: the key is
        // fixed by the payment, how much was already refunded and this amount.
        $idempotencyKey = substr(hash('sha256', "{$payment->uuid}|{$payment->order->refunded_total}|{$amount}"), 0, 32);
        $response = $this->http(retry: false)->withHeaders(['X-Refund-Idempotency' => $idempotencyKey])
            ->post(self::API."/payments/{$payment->provider_payment_id}/refund", ['amount' => $amount, 'notes' => ['reason' => $reason]]);
        if (! $response->successful()) {
            Log::channel('payments')->error('Razorpay refund failed.', ['status' => $response->status(), 'payment' => $payment->uuid]);
            throw new ApiException('The payment provider rejected the refund.', 502, 'gateway_error');
        }

        return (string) $response->json('id');
    }

    /**
     * Maps a Razorpay payment entity to an event. Authorized-but-uncaptured money (auto-capture
     * switched off in the dashboard) is captured here, or Razorpay would refund it after a few days.
     *
     * @param  array<string, mixed>  $entity
     */
    private function eventFromRemote(Payment $payment, array $entity): GatewayEvent
    {
        $paymentId = (string) ($entity['id'] ?? '');
        $status = (string) ($entity['status'] ?? '');
        $amount = (int) ($entity['amount'] ?? 0);
        $currency = strtoupper((string) ($entity['currency'] ?? ''));

        if ($status === 'authorized') {
            if ($amount !== $payment->amount || $currency !== $payment->currency) {
                Log::channel('payments')->critical('Authorized amount does not match the order; not capturing.', ['payment' => $payment->uuid, 'expected' => $payment->amount, 'got' => $amount]);
            } else {
                $capture = $this->http(retry: false)->post(self::API."/payments/{$paymentId}/capture", ['amount' => $amount, 'currency' => $currency]);
                $status = $capture->successful() ? (string) $capture->json('status') : 'authorized';
            }
        }

        return new GatewayEvent(
            eventId: "rzp_{$paymentId}_{$status}",
            type: match ($status) {
                'captured' => GatewayEvent::CAPTURED,
                'failed' => GatewayEvent::FAILED,
                default => GatewayEvent::PENDING,
            },
            providerOrderId: $payment->provider_order_id,
            providerPaymentId: $paymentId,
            amount: $amount,
            currency: $currency,
            method: $entity['method'] ?? null,
            failureMessage: $entity['error_description'] ?? null,
            payload: ['status' => $status, 'payment_id' => $paymentId],
        );
    }

    /** Reads are retried on hiccups; anything that moves money is sent exactly once. */
    private function http(bool $retry = true): PendingRequest
    {
        $request = Http::withBasicAuth((string) config('payments.razorpay.key_id'), (string) config('payments.razorpay.key_secret'))
            ->acceptJson()->timeout(15);

        return $retry ? $request->retry(2, 300, throw: false) : $request;
    }
}
