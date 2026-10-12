<?php

use App\Models\ShippingMethod;

function bagWithAddress($test): array
{
    $product = makeProduct(price: 100_000);
    $test->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf($product)->uuid, 'quantity' => 1])->assertCreated();
    $test->putJson('/api/v1/checkout/contact', ['email' => 'guest@example.test'])->assertOk();

    return $test->putJson('/api/v1/checkout/address', ['name' => 'Asha', 'line1' => '1 Marina Road', 'city' => 'Chennai', 'state_code' => 'TN', 'postal_code' => '600001', 'country_code' => 'IN'])
        ->assertOk()->json('data');
}

it('uses the only active method automatically, even when it is not "standard"', function () {
    makeProduct(); // seeds delivery methods
    ShippingMethod::query()->where('zone', 'domestic')->where('code', 'standard')->update(['is_active' => false]);

    $cart = bagWithAddress($this);

    expect($cart['shipping_options'])->toHaveCount(1)
        ->and($cart['shipping_method']['code'])->toBe('express')
        ->and($cart['totals']['shipping']['amount'])->toBe(60_000);
});

it('only accepts delivery methods that are switched on', function () {
    makeProduct();
    ShippingMethod::query()->where('zone', 'domestic')->where('code', 'express')->update(['is_active' => false]);
    bagWithAddress($this);

    $this->putJson('/api/v1/checkout/shipping-method', ['code' => 'express'])->assertStatus(422)->assertJsonValidationErrors('code');
    $this->putJson('/api/v1/checkout/shipping-method', ['code' => 'standard'])->assertOk();
});

it('lists active domestic delivery methods for product pages', function () {
    makeProduct();
    $this->getJson('/api/v1/site-content')->assertOk()->assertJsonCount(2, 'data.delivery_options')
        ->assertJsonPath('data.delivery_options.0.name', 'Standard delivery')
        ->assertJsonPath('data.delivery_options.0.days_max', 5)
        ->assertJsonPath('data.delivery_options.0.amount.amount', 25_000);

    ShippingMethod::query()->where('zone', 'domestic')->where('code', 'express')->update(['is_active' => false]);
    $this->getJson('/api/v1/site-content')->assertJsonCount(1, 'data.delivery_options');
});
