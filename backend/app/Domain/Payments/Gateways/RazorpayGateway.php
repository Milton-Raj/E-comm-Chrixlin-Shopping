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
        $response = $this->http()->post(self::API.'/orders', [
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

        $status = (string) $remote->json('status');

        return new GatewayEvent(
            eventId: "rzp_confirm_{$paymentId}_{$status}",
            type: $status === 'captured' ? GatewayEvent::CAPTURED : ($status === 'failed' ? GatewayEvent::FAILED : 'pending'),
            providerOrderId: $orderId,
            providerPaymentId: $paymentId,
            amount: (int) $remote->json('amount'),
            currency: (string) $remote->json('currency'),
            method: $remote->json('method'),
            failureMessage: $remote->json('error_description'),
        );
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
        $response = $this->http()->post(self::API."/payments/{$payment->provider_payment_id}/refund", ['amount' => $amount, 'notes' => ['reason' => $reason]]);
        if (! $response->successful()) {
            throw new ApiException('The payment provider rejected the refund.', 502, 'gateway_error');
        }

        return (string) $response->json('id');
    }

    private function http(): PendingRequest
    {
        return Http::withBasicAuth((string) config('payments.razorpay.key_id'), (string) config('payments.razorpay.key_secret'))
            ->acceptJson()->timeout(15)->retry(2, 300, throw: false);
    }
}
