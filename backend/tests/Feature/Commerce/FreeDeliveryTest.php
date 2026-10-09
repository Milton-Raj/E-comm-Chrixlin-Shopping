<?php

use App\Models\User;

function deliveryAddress($test): void
{
    $test->putJson('/api/v1/checkout/contact', ['email' => 'guest@example.test'])->assertOk();
    $test->putJson('/api/v1/checkout/address', ['name' => 'Asha', 'line1' => '1 Marina Road', 'city' => 'Chennai', 'state_code' => 'TN', 'postal_code' => '600001', 'country_code' => 'IN'])->assertOk();
}

it('charges no delivery when every item in the bag has free delivery', function () {
    $free = makeProduct(['free_shipping' => true], price: 150_000);
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($free)->uuid, 'quantity' => 2])->assertCreated();
    deliveryAddress($this);

    $cart = $this->putJson('/api/v1/checkout/shipping-method', ['code' => 'standard'])->assertOk()->json('data');

    expect(collect($cart['shipping_options'])->pluck('amount.amount')->unique()->all())->toBe([0])
        ->and($cart['totals']['shipping']['amount'])->toBe(0)
        ->and($cart['totals']['total']['amount'])->toBe(300_000);
});

it('charges the normal delivery once when the bag mixes free and paid items', function () {
    $free = makeProduct(['free_shipping' => true], price: 150_000);
    $paid = makeProduct(price: 100_000);
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($free)->uuid, 'quantity' => 1])->assertCreated();
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($paid)->uuid, 'quantity' => 1])->assertCreated();
    deliveryAddress($this);

    $cart = $this->putJson('/api/v1/checkout/shipping-method', ['code' => 'standard'])->assertOk()->json('data');

    expect($cart['totals']['shipping']['amount'])->toBe(25_000)
        ->and($cart['totals']['total']['amount'])->toBe(275_000);
});

it('saves the free delivery choice from the product editor', function () {
    $admin = User::factory()->staff('product-manager')->create();
    $product = makeProduct(['slug' => 'berry-cake-candle']);

    $payload = $this->actingAs($admin)->getJson("/api/v1/admin/products/{$product->uuid}")->assertOk()->json('data');
    $payload['free_shipping'] = true;
    $payload['tax_class'] = $payload['tax_class']['uuid'] ?? null;
    $payload['category'] = $payload['category']['uuid'] ?? null;
    $payload['brand'] = $payload['brand']['uuid'] ?? null;

    $this->actingAs($admin)->putJson("/api/v1/admin/products/{$product->uuid}", $payload)->assertOk()->assertJsonPath('data.free_shipping', true);
    $this->getJson("/api/v1/products/{$product->slug}")->assertOk()->assertJsonPath('data.free_shipping', true);
});
