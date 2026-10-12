<?php

use App\Domain\Invoices\InvoiceDocument;
use App\Models\Order;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Support\Str;

function offerGiftWrap(int $price = 11_800): void
{
    app(Settings::class)->set('marketing.gift_wrap_enabled', true);
    app(Settings::class)->set('marketing.gift_wrap_price', $price);
}

function giftBag($test, int $price = 100_000): void
{
    $test->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct(price: $price))->uuid, 'quantity' => 1])->assertCreated();
}

it('adds the gift wrapping charge and its GST to the bag, with the message', function () {
    offerGiftWrap(11_800); // ₹118 incl. 18% GST
    giftBag($this);

    $cart = $this->putJson('/api/v1/checkout/gift', ['gift_wrap' => true, 'gift_message' => 'Happy Diwali, Amma!'])->assertOk()->json('data');

    expect($cart['totals']['gift_wrap']['amount'])->toBe(11_800)
        ->and($cart['totals']['total']['amount'])->toBe(100_000 + 25_000 + 11_800)
        ->and($cart['gift'])->toMatchArray(['available' => true, 'wrap' => true, 'message' => 'Happy Diwali, Amma!']);

    $off = $this->putJson('/api/v1/checkout/gift', ['gift_wrap' => false, 'gift_message' => 'ignored'])->assertOk()->json('data');
    expect($off['totals']['gift_wrap']['amount'])->toBe(0)->and($off['gift']['message'])->toBeNull();
});

it('refuses gift wrapping when the owner does not offer it, and never charges for a stale choice', function () {
    giftBag($this);
    expect($this->putJson('/api/v1/checkout/gift', ['gift_wrap' => true]))->toBeApiError(409, 'gift_wrap_unavailable');

    offerGiftWrap();
    $this->putJson('/api/v1/checkout/gift', ['gift_wrap' => true])->assertOk();
    app(Settings::class)->set('marketing.gift_wrap_enabled', false); // switched off mid-checkout

    $cart = $this->getJson('/api/v1/cart')->json('data');
    expect($cart['totals']['gift_wrap']['amount'])->toBe(0)->and($cart['gift']['wrap'])->toBeFalse();
});

it('does not offer gift wrapping for digital-only bags', function () {
    offerGiftWrap();
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct(type: 'digital'))->uuid, 'quantity' => 1])->assertCreated();
    $this->putJson('/api/v1/checkout/gift', ['gift_wrap' => true])->assertOk();

    $cart = $this->getJson('/api/v1/cart')->json('data');
    expect($cart['gift']['available'])->toBeFalse()->and($cart['totals']['gift_wrap']['amount'])->toBe(0);
});

it('carries gift wrapping into the order, payment and invoice', function () {
    offerGiftWrap(11_800);
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    giftBag($this);
    $this->putJson('/api/v1/checkout/contact', ['email' => $customer->email]);
    $this->putJson('/api/v1/checkout/address', ['name' => 'C', 'line1' => 'x', 'city' => 'Chennai', 'state_code' => 'TN', 'postal_code' => '600001', 'country_code' => 'IN']);
    $this->putJson('/api/v1/checkout/gift', ['gift_wrap' => true, 'gift_message' => 'For you'])->assertOk();
    $number = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test'])->assertCreated()->json('data.order_number');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success'])->assertOk();

    $order = Order::query()->where('order_number', $number)->firstOrFail();
    expect($order->gift_wrap_total)->toBe(11_800)
        ->and($order->gift_wrap_tax)->toBe(1_800)
        ->and($order->gift_message)->toBe('For you')
        ->and($order->grand_total)->toBe(100_000 + 25_000 + 11_800)
        ->and($order->payments()->value('amount'))->toBe($order->grand_total);

    $data = app(InvoiceDocument::class)->data($order->invoice()->firstOrFail());
    expect(collect($data['rows'])->pluck('name'))->toContain('Gift wrapping');

    $this->getJson("/api/v1/orders/{$number}")->assertOk()->assertJsonPath('data.gift.message', 'For you')->assertJsonPath('data.totals.gift_wrap.amount', 11_800);
});

it('validates the gift message length', function () {
    offerGiftWrap();
    giftBag($this);
    $this->putJson('/api/v1/checkout/gift', ['gift_wrap' => true, 'gift_message' => str_repeat('x', 301)])->assertStatus(422)->assertJsonValidationErrors('gift_message');
});
