<?php

use App\Models\User;
use Database\Seeders\StoreSetupSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    (new StoreSetupSeeder)->run();
    $this->admin = User::factory()->staff('administrator')->create();
});

it('gives every new user the next readable customer number', function () {
    $first = User::factory()->customer()->create();
    $second = User::factory()->customer()->create();

    expect($first->customer_number)->toMatch('/^CUS-\d{6}$/')
        ->and((int) substr($second->customer_number, 4))->toBe((int) substr($first->customer_number, 4) + 1);
});

it('opens a customer by customer number as well as uuid', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($this->admin)->getJson("/api/v1/admin/customers/{$customer->customer_number}")
        ->assertOk()->assertJsonPath('data.uuid', $customer->uuid)->assertJsonPath('data.customer_number', $customer->customer_number);
    $this->getJson("/api/v1/admin/customers/{$customer->uuid}")->assertOk();
    $this->getJson('/api/v1/admin/customers')->assertOk()->assertJsonPath('data.0.customer_number', $customer->customer_number);
});

it('opens a product by slug as well as uuid', function () {
    $uuid = $this->actingAs($this->admin)->postJson('/api/v1/admin/products', [
        'name' => 'Silk Scarf', 'product_type' => 'physical', 'status' => 'active',
        'variants' => [['sku' => 'SILK-1', 'price' => 199_900, 'opening_stock' => 2]],
    ])->assertCreated()->json('data.uuid');

    $this->getJson('/api/v1/admin/products/silk-scarf')->assertOk()->assertJsonPath('data.uuid', $uuid);
    $this->getJson("/api/v1/admin/products/{$uuid}")->assertOk()->assertJsonPath('data.slug', 'silk-scarf');
    $this->getJson('/api/v1/admin/products/no-such-product')->assertNotFound();
});

it('opens a content page by slug as well as uuid', function () {
    $uuid = $this->actingAs($this->admin)->postJson('/api/v1/admin/pages', [
        'title' => 'Our Story', 'slug' => 'our-story', 'status' => 'draft',
    ])->assertSuccessful()->json('data.uuid');

    $this->getJson('/api/v1/admin/pages/our-story')->assertOk()->assertJsonPath('data.uuid', $uuid);
    $this->getJson("/api/v1/admin/pages/{$uuid}")->assertOk();
});

it('still requires permission to open a customer by number', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs(User::factory()->customer()->create())
        ->getJson("/api/v1/admin/customers/{$customer->customer_number}")->assertForbidden();
});
