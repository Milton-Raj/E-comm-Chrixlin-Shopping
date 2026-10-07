<?php

use App\Domain\Cart\CartService;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;

it('creates a guest cart behind an httpOnly cookie and prices it on the server', function () {
    $variant = variantOf(makeProduct(price: 250_000));

    $response = $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 2, 'price' => 1])
        ->assertCreated()
        ->assertJsonPath('data.item_count', 2)
        ->assertJsonPath('data.totals.subtotal.amount', 500_000)
        ->assertJsonPath('data.totals.total.amount', 500_000);

    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === CartService::COOKIE);
    expect($cookie)->not->toBeNull()->and($cookie->isHttpOnly())->toBeTrue();
});

it('clamps quantity to available stock and removes items', function () {
    $variant = variantOf(makeProduct(stock: 3));
    $item = $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 9])->json('data.items.0');

    expect($item['quantity'])->toBe(3);
    $this->patchJson("/api/v1/cart/items/{$item['uuid']}", ['quantity' => 1])->assertJsonPath('data.items.0.quantity', 1);
    $this->deleteJson("/api/v1/cart/items/{$item['uuid']}")->assertJsonPath('data.item_count', 0);
});

it('refuses sold-out items', function () {
    $variant = variantOf(makeProduct(stock: 0));
    expect($this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]))->toBeApiError(409, 'out_of_stock');
});

it('applies valid coupons and rejects invalid, expired and below-minimum ones', function () {
    $variant = variantOf(makeProduct(price: 300_000));
    Coupon::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 1000, 'is_active' => true]);
    Coupon::create(['code' => 'OLD', 'type' => 'percentage', 'value' => 1000, 'is_active' => true, 'ends_at' => now()->subDay()]);
    Coupon::create(['code' => 'BIG', 'type' => 'fixed', 'value' => 50_000, 'min_order_total' => 500_000, 'is_active' => true]);
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $variant->uuid, 'quantity' => 1]);

    expect($this->postJson('/api/v1/cart/coupon', ['code' => 'NOPE']))->toBeApiError(422, 'coupon_invalid');
    expect($this->postJson('/api/v1/cart/coupon', ['code' => 'old']))->toBeApiError(422, 'coupon_expired');
    expect($this->postJson('/api/v1/cart/coupon', ['code' => 'BIG']))->toBeApiError(422, 'coupon_minimum_not_met');

    $this->postJson('/api/v1/cart/coupon', ['code' => 'ten'])->assertOk()
        ->assertJsonPath('data.coupon.code', 'TEN')
        ->assertJsonPath('data.totals.discount.amount', 30_000)
        ->assertJsonPath('data.totals.total.amount', 270_000);
});

it('merges the guest cart into the customer cart on login', function () {
    $user = User::factory()->customer()->create(['password' => 'correct-horse-battery']);
    $a = variantOf(makeProduct());
    $b = variantOf(makeProduct());
    Cart::create(['token' => $existing = (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'active', 'currency' => 'INR'])
        ->items()->create(['variant_id' => $a->id, 'quantity' => 1]);

    $this->postJson('/api/v1/cart/items', ['variant_uuid' => $b->uuid, 'quantity' => 1]);
    $guestToken = Cart::query()->whereNull('user_id')->value('token');

    $this->withUnencryptedCookie(CartService::COOKIE, $guestToken)->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-horse-battery'])->assertOk();

    $cart = Cart::query()->where('token', $existing)->first();
    expect($cart->items()->count())->toBe(2)
        ->and(Cart::query()->where('token', $guestToken)->value('status'))->toBe('merged');
});
