<?php

use App\Domain\Inventory\InventoryService;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Notifications\OrderPaidNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

function fillCheckout($test, array $address = []): void
{
    $test->putJson('/api/v1/checkout/contact', ['email' => 'guest@example.test', 'phone' => '9876543210'])->assertOk();
    $test->putJson('/api/v1/checkout/address', array_merge([
        'name' => 'Asha', 'line1' => '1 Marina Road', 'city' => 'Chennai', 'state_code' => 'TN', 'postal_code' => '600001', 'country_code' => 'IN',
    ], $address))->assertOk();
}

function placeOrder($test, array $body = ['gateway' => 'test'], ?string $key = null)
{
    return $test->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson('/api/v1/checkout/place-order', $body);
}

beforeEach(fn () => Notification::fake());

it('places an order with server prices, GST and shipping, and holds stock', function () {
    $variant = variantOf(makeProduct(stock: 5, price: 118_000));
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 2]);
    fillCheckout($this);

    $placed = placeOrder($this)->assertCreated();
    $order = Order::query()->where('order_number', $placed->json('data.order_number'))->firstOrFail();

    expect($order->order_number)->toMatch('/^ORD-\d{4}-\d{6}$/')
        ->and($order->subtotal)->toBe(236_000)
        ->and($order->shipping_total)->toBe(25_000)         // standard, below free threshold
        ->and($order->tax_total)->toBe(36_000)              // 18% inclusive of 2,360
        ->and($order->tax_breakdown)->toBe(['CGST' => 18_000, 'SGST' => 18_000]) // TN -> TN intra-state
        ->and($order->grand_total)->toBe(261_000)
        ->and($order->status->value)->toBe('pending')
        ->and($variant->inventory->fresh()->reserved)->toBe(2)
        ->and($placed->json('data.access_token'))->not->toBeNull();
});

it('estimates GST as in-state before an address, and IGST for another state', function () {
    $variant = variantOf(makeProduct(price: 118_000));
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1])
        ->assertJsonPath('data.tax_breakdown.0.code', 'CGST');

    fillCheckout($this, ['state_code' => 'KA', 'postal_code' => '560001', 'city' => 'Bengaluru']);
    $this->getJson('/api/v1/cart')->assertJsonPath('data.tax_breakdown.0.code', 'IGST')->assertJsonPath('data.totals.tax.amount', 18_000);
});

it('marks the order paid only after the server verifies the payment', function () {
    $variant = variantOf(makeProduct(stock: 5));
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 2]);
    fillCheckout($this);
    $placed = placeOrder($this);
    $number = $placed->json('data.order_number');
    $token = $placed->json('data.access_token');

    // An unverifiable confirmation changes nothing.
    expect($this->withHeaders(['X-Order-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'paid-trust-me']))->toBeApiError(422, 'payment_unverified');

    $this->withHeaders(['X-Order-Token' => $token, 'Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success'])
        ->assertOk()->assertJsonPath('data.payment_status', 'captured')->assertJsonPath('data.status', 'processing');

    $inventory = $variant->inventory->fresh();
    expect($inventory->on_hand)->toBe(3)->and($inventory->reserved)->toBe(0)
        ->and(Cart::query()->latest('id')->value('status'))->toBe('converted');
    Notification::assertSentOnDemand(OrderPaidNotification::class);
});

it('processes a duplicated payment event only once', function () {
    $variant = variantOf(makeProduct(stock: 5));
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]);
    fillCheckout($this);
    $placed = placeOrder($this);
    $headers = ['X-Order-Token' => $placed->json('data.access_token')];
    $url = '/api/v1/orders/'.$placed->json('data.order_number').'/payment/confirm';

    $this->withHeaders($headers + ['Idempotency-Key' => (string) Str::uuid()])->postJson($url, ['outcome' => 'success'])->assertOk();
    $this->withHeaders($headers + ['Idempotency-Key' => (string) Str::uuid()])->postJson($url, ['outcome' => 'success'])->assertOk();

    expect($variant->inventory->fresh()->on_hand)->toBe(4)
        ->and(WebhookEvent::query()->count())->toBe(1)
        ->and(PaymentTransaction::query()->where('type', 'capture')->count())->toBe(1);
});

it('keeps the order payable after a failed payment and releases stock when it expires', function () {
    $variant = variantOf(makeProduct(stock: 2));
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 2]);
    fillCheckout($this);
    $placed = placeOrder($this);
    $number = $placed->json('data.order_number');

    $this->withHeaders(['X-Order-Token' => $placed->json('data.access_token'), 'Idempotency-Key' => (string) Str::uuid()])
        ->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'failure'])->assertJsonPath('data.payment_status', 'failed');
    expect($variant->inventory->fresh()->reserved)->toBe(2);

    $this->travel(2)->hours();
    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect(Order::query()->where('order_number', $number)->first()->status->value)->toBe('cancelled')
        ->and($variant->inventory->fresh()->reserved)->toBe(0)
        ->and($variant->inventory->fresh()->on_hand)->toBe(2);
});

it('prevents overselling the last unit', function () {
    $variant = variantOf(makeProduct(stock: 1));
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]);
    fillCheckout($this);
    // Someone else buys it meanwhile.
    app(InventoryService::class)->adjust($variant, 'adjustment', -1);

    expect(placeOrder($this))->toBeApiError(409);
    expect(Order::query()->count())->toBe(0);
});

it('rejects a changed total and requires an idempotency key', function () {
    $variant = variantOf(makeProduct());
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]);
    fillCheckout($this);

    expect($this->postJson('/api/v1/checkout/place-order', ['gateway' => 'test']))->toBeApiError(400, 'idempotency_key_required');
    expect(placeOrder($this, ['gateway' => 'test', 'expected_total' => 1]))->toBeApiError(409, 'price_changed');
});

it('replays the same response for a repeated idempotency key', function () {
    $variant = variantOf(makeProduct());
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]);
    fillCheckout($this);

    $first = placeOrder($this, key: 'same-key-123');
    $second = placeOrder($this, key: 'same-key-123')->assertHeader('Idempotent-Replayed', 'true');

    expect($second->json('data.order_number'))->toBe($first->json('data.order_number'))
        ->and(Order::query()->count())->toBe(1);
});

it('delivers digital-only orders instantly without an address', function () {
    $product = makeProduct(type: 'digital', price: 89_900);
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($product)->uuid, 'quantity' => 3])->assertJsonPath('data.items.0.quantity', 1);
    $this->putJson('/api/v1/checkout/contact', ['email' => 'reader@example.test'])->assertOk()->assertJsonPath('data.requires_shipping', false);

    $placed = placeOrder($this)->assertCreated();
    $this->withHeaders(['X-Order-Token' => $placed->json('data.access_token'), 'Idempotency-Key' => (string) Str::uuid()])
        ->postJson('/api/v1/orders/'.$placed->json('data.order_number').'/payment/confirm', ['outcome' => 'success'])
        ->assertJsonPath('data.status', 'delivered');

    $order = Order::query()->firstOrFail();
    expect($order->shipping_total)->toBe(0)->and($order->entitlements()->count())->toBe(1);
});

it('never shows an order to someone who does not own it', function () {
    $variant = variantOf(makeProduct());
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]);
    fillCheckout($this);
    $number = placeOrder($this)->json('data.order_number');

    $this->getJson("/api/v1/orders/{$number}")->assertNotFound();
    $this->withHeader('X-Order-Token', 'wrong')->getJson("/api/v1/orders/{$number}")->assertNotFound();
    $this->actingAs(User::factory()->customer()->create())->getJson("/api/v1/orders/{$number}")->assertNotFound();
});

it('authenticates payment webhooks and ignores duplicates', function () {
    $variant = variantOf(makeProduct(stock: 3));
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]);
    fillCheckout($this);
    $order = Order::query()->where('order_number', placeOrder($this)->json('data.order_number'))->firstOrFail();
    $payment = $order->payments()->firstOrFail();

    $body = json_encode(['id' => 'evt_1', 'type' => 'payment.captured', 'order_id' => $payment->provider_order_id, 'payment_id' => 'pay_1', 'amount' => $payment->amount, 'currency' => 'INR']);
    $sign = fn (string $b) => hash_hmac('sha256', $b, (string) config('payments.test.webhook_secret'));

    $this->call('POST', '/api/webhooks/payment/test', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TEST_SIGNATURE' => 'bad'], $body)->assertStatus(401);
    $this->call('POST', '/api/webhooks/payment/test', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TEST_SIGNATURE' => $sign($body)], $body)->assertOk()->assertJsonPath('status', 'processed');
    $this->call('POST', '/api/webhooks/payment/test', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TEST_SIGNATURE' => $sign($body)], $body)->assertOk()->assertJsonPath('status', 'duplicate');

    expect($order->fresh()->payment_status->value)->toBe('captured')->and($variant->inventory->fresh()->on_hand)->toBe(2);
});

it('flags a captured amount that does not match the order', function () {
    $variant = variantOf(makeProduct());
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]);
    fillCheckout($this);
    $order = Order::query()->where('order_number', placeOrder($this)->json('data.order_number'))->firstOrFail();
    $payment = $order->payments()->firstOrFail();

    $body = json_encode(['id' => 'evt_2', 'type' => 'payment.captured', 'order_id' => $payment->provider_order_id, 'payment_id' => 'pay_2', 'amount' => 1, 'currency' => 'INR']);
    $this->call('POST', '/api/webhooks/payment/test', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_TEST_SIGNATURE' => hash_hmac('sha256', $body, (string) config('payments.test.webhook_secret'))], $body);

    expect($order->fresh()->payment_status->value)->not->toBe('captured')->and($order->fresh()->requires_attention)->toBeTrue();
});

it('counts a coupon once per paid order, however often the payment is confirmed', function () {
    $coupon = Coupon::query()->create(['code' => 'ONCE10', 'type' => 'percentage', 'value' => 1000, 'is_active' => true]);
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct())->uuid, 'quantity' => 1])->assertSuccessful();
    $this->postJson('/api/v1/cart/coupon', ['code' => 'ONCE10'])->assertOk();
    $this->putJson('/api/v1/checkout/contact', ['email' => $customer->email]);
    $this->putJson('/api/v1/checkout/address', ['name' => 'C', 'line1' => 'x', 'city' => 'Chennai', 'state_code' => 'TN', 'postal_code' => '600001', 'country_code' => 'IN']);
    $number = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test'])->json('data.order_number');

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'failure']);
    expect($coupon->fresh()->usage_count)->toBe(0);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success']);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success']);

    expect($coupon->fresh()->usage_count)->toBe(1)
        ->and(DB::table('coupon_usages')->where('coupon_id', $coupon->id)->count())->toBe(1);
});
