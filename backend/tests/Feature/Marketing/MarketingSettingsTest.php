<?php

use App\Models\User;

function marketingPayload(array $marketing = []): array
{
    return [
        'store' => ['name' => 'Chrixlin', 'state_code' => 'TN'],
        'marketing' => array_merge([
            'low_stock_count' => 3, 'whatsapp_number' => '+91 98765 43210', 'gift_wrap_enabled' => true, 'gift_wrap_price' => 4_900,
            'abandoned_cart_enabled' => true, 'abandoned_cart_delay_hours' => 4,
        ], $marketing),
    ];
}

it('lets admins save marketing settings and audits the change', function () {
    $this->actingAs(User::factory()->staff('administrator')->create());

    $this->putJson('/api/v1/admin/settings', marketingPayload())->assertOk();

    $this->getJson('/api/v1/admin/settings')->assertOk()
        ->assertJsonPath('data.marketing.whatsapp_number', '919876543210')
        ->assertJsonPath('data.marketing.gift_wrap_price', 4_900)
        ->assertJsonPath('data.marketing.abandoned_cart_delay_hours', 4);
    $this->getJson('/api/v1/site-content')->assertOk()->assertJsonPath('data.whatsapp_number', '919876543210');
});

it('validates marketing settings', function () {
    $this->actingAs(User::factory()->staff('administrator')->create());

    $this->putJson('/api/v1/admin/settings', marketingPayload(['whatsapp_number' => 'call me', 'abandoned_cart_delay_hours' => 0, 'low_stock_count' => 99]))
        ->assertStatus(422)->assertJsonValidationErrors(['marketing.whatsapp_number', 'marketing.abandoned_cart_delay_hours', 'marketing.low_stock_count']);
});

it('keeps marketing settings away from staff without settings access', function () {
    $this->actingAs(User::factory()->staff('product-manager')->create());

    $this->putJson('/api/v1/admin/settings', marketingPayload())->assertForbidden();
});

it('shows the exact count only at or below the low-stock threshold', function () {
    $this->actingAs(User::factory()->staff('administrator')->create());
    $this->putJson('/api/v1/admin/settings', marketingPayload(['low_stock_count' => 3]))->assertOk();
    auth()->guard('web')->logout();

    $few = makeProduct(stock: 2);
    $plenty = makeProduct(stock: 8);

    $this->getJson("/api/v1/products/{$few->slug}")->assertJsonPath('data.stock_status', 'low_stock')->assertJsonPath('data.low_stock_count', 2);
    $this->getJson("/api/v1/products/{$plenty->slug}")->assertJsonPath('data.stock_status', 'in_stock')->assertJsonPath('data.low_stock_count', null);
});
