<?php

use App\Domain\Payments\Actions\ReconcilePayments;
use App\Domain\Payments\Gateways\RazorpayGateway;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Notifications\OrderPaidNotification;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
| Razorpay over a faked HTTP API: the browser's word is never enough, money moves once,
| and a payment the shop never heard about still ends as a paid order.
*/

beforeEach(function () {
    Notification::fake();
    config([
        'payments.razorpay.key_id' => 'rzp_test_key',
        'payments.razorpay.key_secret' => 'rzp_secret',
        'payments.razorpay.webhook_secret' => 'rzp_webhook_secret',
    ]);
});

/** @param array<string, mixed> $overrides */
function rzpPayment(Payment $payment, string $status, array $overrides = []): array
{
    return array_merge([
        'id' => 'pay_RZP123', 'entity' => 'payment', 'order_id' => $payment->provider_order_id, 'status' => $status,
        'amount' => $payment->amount, 'currency' => 'INR', 'method' => 'upi', 'error_description' => $status === 'failed' ? 'Declined' : null,
    ], $overrides);
}

/** Places an order paid with Razorpay; returns [order, payment, guest token]. */
function razorpayOrder($test): array
{
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_RZP1', 'status' => 'created'])]);
    $test->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct(stock: 5))->uuid, 'quantity' => 1]);
    $test->putJson('/api/v1/checkout/contact', ['email' => 'guest@example.test', 'phone' => '9876543210'])->assertOk();
    $test->putJson('/api/v1/checkout/address', ['name' => 'Asha', 'line1' => '1 Marina Road', 'city' => 'Chennai', 'state_code' => 'TN', 'postal_code' => '600001', 'country_code' => 'IN'])->assertOk();
    $placed = $test->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'razorpay'])->assertCreated()
        ->assertJsonPath('data.payment.client_payload.mode', 'razorpay')
        ->assertJsonPath('data.payment.client_payload.order_id', 'order_RZP1')
        ->assertJsonPath('data.payment.client_payload.key_id', 'rzp_test_key');

    $order = Order::query()->where('order_number', $placed->json('data.order_number'))->firstOrFail();

    return [$order, $order->payments()->firstOrFail(), $placed->json('data.access_token')];
}

function confirmRazorpay($test, Order $order, string $token, array $body)
{
    return $test->withHeaders(['X-Order-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/orders/{$order->order_number}/payment/confirm", $body);
}

function rzpSignature(string $orderId, string $paymentId): string
{
    return hash_hmac('sha256', "{$orderId}|{$paymentId}", 'rzp_secret');
}

it('never sends the key secret to the browser', function () {
    razorpayOrder($this);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.razorpay.com/v1/orders' && $r['amount'] > 0 && $r['currency'] === 'INR');
    expect(json_encode(Order::query()->latest('id')->first()->payments()->first()))->not->toContain('rzp_secret');
});

it('marks the order paid only after a valid signature and a captured status from Razorpay', function () {
    [$order, $payment, $token] = razorpayOrder($this);
    Http::fake(['api.razorpay.com/v1/payments/pay_RZP123' => Http::response(rzpPayment($payment, 'captured'))]);

    confirmRazorpay($this, $order, $token, ['razorpay_order_id' => 'order_RZP1', 'razorpay_payment_id' => 'pay_RZP123', 'razorpay_signature' => 'forged'])
        ->assertStatus(422);
    expect($order->fresh()->payment_status->value)->toBe('pending');

    confirmRazorpay($this, $order, $token, ['razorpay_order_id' => 'order_RZP1', 'razorpay_payment_id' => 'pay_RZP123', 'razorpay_signature' => rzpSignature('order_RZP1', 'pay_RZP123')])
        ->assertOk()->assertJsonPath('data.payment_status', 'captured');

    expect($payment->fresh()->provider_payment_id)->toBe('pay_RZP123')->and($payment->fresh()->method)->toBe('upi');
    Notification::assertSentOnDemand(OrderPaidNotification::class);
});

it('does not trust a valid signature when Razorpay says the payment failed', function () {
    [$order, $payment, $token] = razorpayOrder($this);
    Http::fake(['api.razorpay.com/v1/payments/pay_RZP123' => Http::response(rzpPayment($payment, 'failed'))]);

    confirmRazorpay($this, $order, $token, ['razorpay_order_id' => 'order_RZP1', 'razorpay_payment_id' => 'pay_RZP123', 'razorpay_signature' => rzpSignature('order_RZP1', 'pay_RZP123')])
        ->assertOk()->assertJsonPath('data.payment_status', 'failed');
});

it('captures an authorized payment when auto-capture is off', function () {
    [$order, $payment, $token] = razorpayOrder($this);
    Http::fake([
        'api.razorpay.com/v1/payments/pay_RZP123/capture' => Http::response(rzpPayment($payment, 'captured')),
        'api.razorpay.com/v1/payments/pay_RZP123' => Http::response(rzpPayment($payment, 'authorized')),
    ]);

    confirmRazorpay($this, $order, $token, ['razorpay_order_id' => 'order_RZP1', 'razorpay_payment_id' => 'pay_RZP123', 'razorpay_signature' => rzpSignature('order_RZP1', 'pay_RZP123')])
        ->assertOk()->assertJsonPath('data.payment_status', 'captured');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/capture') && $r['amount'] === $payment->amount && $r['currency'] === 'INR');
});

it('does not capture an authorized amount that differs from the order', function () {
    [$order, $payment, $token] = razorpayOrder($this);
    Http::fake(['api.razorpay.com/v1/payments/pay_RZP123' => Http::response(rzpPayment($payment, 'authorized', ['amount' => 100]))]);

    confirmRazorpay($this, $order, $token, ['razorpay_order_id' => 'order_RZP1', 'razorpay_payment_id' => 'pay_RZP123', 'razorpay_signature' => rzpSignature('order_RZP1', 'pay_RZP123')]);

    Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/capture'));
    expect($order->fresh()->payment_status->value)->not->toBe('captured');
});

it('accepts only signed webhooks and applies each event once', function () {
    [$order, $payment] = razorpayOrder($this);
    $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => rzpPayment($payment, 'captured')]]]);
    $send = fn (string $signature) => $this->call('POST', '/api/webhooks/payment/razorpay', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature, 'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_RZP1',
    ], $body);

    $send('bad')->assertStatus(401);
    $send(hash_hmac('sha256', $body, 'rzp_webhook_secret'))->assertOk()->assertJsonPath('status', 'processed');
    $send(hash_hmac('sha256', $body, 'rzp_webhook_secret'))->assertOk()->assertJsonPath('status', 'duplicate');

    expect($order->fresh()->payment_status->value)->toBe('captured')
        ->and(WebhookEvent::query()->where('provider', 'razorpay')->count())->toBe(1);
});

it('settles a paid order whose webhook and browser confirmation both went missing', function () {
    [$order, $payment] = razorpayOrder($this);
    Http::fake(['api.razorpay.com/v1/orders/order_RZP1/payments' => Http::response(['items' => [
        rzpPayment($payment, 'failed', ['id' => 'pay_FAIL']),
        rzpPayment($payment, 'captured'),
    ]])]);

    $this->travel(11)->minutes();
    expect(app(ReconcilePayments::class)())->toBe(1);

    expect($order->fresh()->payment_status->value)->toBe('captured')
        ->and($payment->fresh()->provider_payment_id)->toBe('pay_RZP123');

    // A second sweep finds nothing left to do.
    expect(app(ReconcilePayments::class)())->toBe(0);
});

it('never expires an unpaid-looking order that Razorpay says was paid', function () {
    [$order, $payment] = razorpayOrder($this);
    Http::fake(['api.razorpay.com/v1/orders/order_RZP1/payments' => Http::response(['items' => [rzpPayment($payment, 'captured')]])]);

    $this->travel(2)->hours();
    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($order->fresh()->status->value)->not->toBe('cancelled')
        ->and($order->fresh()->payment_status->value)->toBe('captured');
});

it('still expires an order that Razorpay has no payment for', function () {
    [$order] = razorpayOrder($this);
    Http::fake(['api.razorpay.com/v1/orders/order_RZP1/payments' => Http::response(['items' => []])]);

    $this->travel(2)->hours();
    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($order->fresh()->status->value)->toBe('cancelled');
});

it('sends a refund once, with an idempotency key, and never retries it blindly', function () {
    [$order, $payment] = razorpayOrder($this);
    $payment->forceFill(['provider_payment_id' => 'pay_RZP123'])->save();

    Http::fake(['api.razorpay.com/v1/payments/pay_RZP123/refund' => Http::sequence()
        ->push(['error' => ['description' => 'Server error']], 500)
        ->push(['id' => 'rfnd_1', 'status' => 'processed'])]);

    expect(fn () => app(RazorpayGateway::class)->refund($payment->fresh(), 5_000, 'Damaged'))->toThrow(ApiException::class);
    Http::assertSentCount(1); // exactly one refund attempt, no automatic retry

    expect(app(RazorpayGateway::class)->refund($payment->fresh(), 5_000, 'Damaged'))->toBe('rfnd_1');

    $keys = collect(Http::recorded())->map(fn ($pair) => $pair[0]->header('X-Refund-Idempotency')[0] ?? null)->filter()->values();
    expect($keys)->toHaveCount(2)->and($keys[0])->toBe($keys[1]); // same refund, same key
});
