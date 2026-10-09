<?php

use App\Models\User;
use Illuminate\Support\Str;

it('offers a returning customer the address from their last order to confirm', function () {
    $order = paidOrder($this, makeProduct());
    $customer = User::find($order->user_id);

    $this->actingAs($customer)->getJson('/api/v1/checkout')->assertOk()
        ->assertJsonPath('data.saved_address.line1', 'x')
        ->assertJsonPath('data.saved_address.city', 'Chennai')
        ->assertJsonPath('data.saved_address.postal_code', '600001')
        // Offered, never applied silently: the new cart has no delivery address until they confirm.
        ->assertJsonPath('data.cart.shipping_address', null);
});

it('uses the most recent address after the customer changes it', function () {
    $order = paidOrder($this, makeProduct());
    $customer = User::find($order->user_id);
    $this->actingAs($customer);
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct())->uuid, 'quantity' => 1]);
    $this->putJson('/api/v1/checkout/contact', ['email' => $customer->email]);
    $this->putJson('/api/v1/checkout/address', ['name' => 'C', 'line1' => '12 New Street', 'city' => 'Coimbatore', 'state_code' => 'TN', 'postal_code' => '641001', 'country_code' => 'IN']);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test'])->assertSuccessful();

    $this->getJson('/api/v1/checkout')->assertJsonPath('data.saved_address.line1', '12 New Street');
});

it('saves nothing for guests and never shows another customer\'s address', function () {
    paidOrder($this, makeProduct());

    $this->flushSession();
    app('auth')->forgetGuards();
    $this->getJson('/api/v1/checkout')->assertOk()->assertJsonPath('data.saved_address', null);

    $stranger = User::factory()->customer()->create();
    $this->actingAs($stranger)->getJson('/api/v1/checkout')->assertOk()->assertJsonPath('data.saved_address', null);
});
