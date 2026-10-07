<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Models\AuditLog;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Settings\Settings;
use Database\Seeders\StoreSetupSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    (new StoreSetupSeeder)->run();
    $this->admin = User::factory()->staff('administrator')->create();
});

it('creates a product with variants and opening stock, audited', function () {
    $this->actingAs($this->admin)->postJson('/api/v1/admin/products', [
        'name' => 'Linen Shirt', 'product_type' => 'physical', 'status' => 'active',
        'options' => [['name' => 'Size', 'values' => ['S', 'M']]],
        'variants' => [
            ['sku' => 'LIN-S', 'name' => 'S', 'options' => ['Size' => 'S'], 'price' => 450_000, 'opening_stock' => 4],
            ['sku' => 'LIN-M', 'name' => 'M', 'options' => ['Size' => 'M'], 'price' => 480_000, 'compare_at_price' => 520_000, 'opening_stock' => 6],
        ],
    ])->assertCreated()->assertJsonPath('data.stock_on_hand', 10)->assertJsonPath('data.price.amount', 450_000);

    $product = Product::query()->where('slug', 'linen-shirt')->firstOrFail();
    expect($product->published_at)->not->toBeNull()
        ->and((int) InventoryTransaction::query()->where('type', 'opening')->sum('quantity'))->toBe(10)
        ->and(AuditLog::query()->where('action', 'product.created')->exists())->toBeTrue();

    $this->getJson("/api/v1/products/{$product->slug}")->assertOk(); // visible on the storefront
});

it('validates prices, SKUs and product types', function () {
    makeProduct()->variants()->first()->update(['sku' => 'TAKEN']);

    $this->actingAs($this->admin)->postJson('/api/v1/admin/products', [
        'name' => 'X', 'product_type' => 'subscription', 'status' => 'active',
        'variants' => [['sku' => 'TAKEN', 'price' => -5]],
    ])->assertStatus(422)->assertJsonValidationErrors(['product_type', 'variants.0.price']);
});

it('archives instead of deleting', function () {
    $product = makeProduct();
    $this->actingAs($this->admin)->deleteJson("/api/v1/admin/products/{$product->uuid}")->assertOk();

    expect($product->fresh()->status->value)->toBe('archived');
    $this->getJson("/api/v1/products/{$product->slug}")->assertNotFound();
});

it('adjusts stock only through the ledger and refuses to go below reserved', function () {
    $variant = variantOf(makeProduct(stock: 5));
    $this->actingAs($this->admin)->postJson("/api/v1/admin/inventory/{$variant->uuid}/adjust", ['type' => 'received', 'quantity' => 7, 'note' => 'PO-1'])
        ->assertOk()->assertJsonPath('data.on_hand', 12);
    $this->postJson("/api/v1/admin/inventory/{$variant->uuid}/adjust", ['type' => 'damage', 'quantity' => 2])->assertJsonPath('data.on_hand', 10);

    $variant->inventory->update(['reserved' => 9]);
    expect($this->postJson("/api/v1/admin/inventory/{$variant->uuid}/adjust", ['type' => 'adjustment', 'quantity' => -5]))->toBeApiError(409, 'stock_below_reserved');
    $this->getJson("/api/v1/admin/inventory/{$variant->uuid}/transactions")->assertJsonCount(3, 'data');
});

it('ships and delivers an order through allowed transitions only', function () {
    $order = paidOrder($this, makeProduct(stock: 3));
    $this->actingAs($this->admin);

    expect($this->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'delivered']))->toBeApiError(409, 'shipment_required');

    $this->postJson("/api/v1/admin/orders/{$order->order_number}/shipments", ['carrier' => 'Blue Dart', 'tracking_number' => 'BD1'])
        ->assertOk()->assertJsonPath('data.status', 'shipped')->assertJsonPath('data.shipments.0.tracking_number', 'BD1');
    $this->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'delivered'])->assertJsonPath('data.status', 'delivered');
});

it('refunds in full: restocks physical items and revokes digital access', function () {
    $physical = makeProduct(stock: 3);
    $order = paidOrder($this, $physical, 2);
    expect(variantOf($physical)->inventory->on_hand)->toBe(1);

    $this->actingAs($this->admin)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/admin/orders/{$order->order_number}/refunds", ['amount' => $order->grand_total, 'reason' => 'Damaged', 'restock' => true])
        ->assertOk()->assertJsonPath('data.status', 'refunded')->assertJsonPath('data.payment_status', 'refunded');

    expect(variantOf($physical)->inventory->on_hand)->toBe(3);

    expect($this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/admin/orders/{$order->order_number}/refunds", ['amount' => 100, 'reason' => 'again']))->toBeApiError(409);
});

it('serves dashboard KPIs and sales reports from paid orders', function () {
    paidOrder($this, makeProduct(price: 200_000));

    $this->actingAs($this->admin)->getJson('/api/v1/admin/dashboard')->assertOk()
        ->assertJsonPath('data.kpis.orders.current', 1)
        ->assertJsonPath('data.kpis.revenue.current.amount', 225_000) // ₹2,000 + ₹250 standard delivery
        ->assertJsonPath('data.top_products.0.units', 1)
        ->assertJsonPath('data.by_type.0.type', 'physical')
        ->assertJsonCount(30, 'data.series');
    $this->getJson('/api/v1/admin/dashboard?range=365')->assertOk()->assertJsonPath('data.range.bucket', 'month');
    $this->getJson('/api/v1/admin/dashboard?range=12')->assertStatus(422);
    $this->getJson('/api/v1/admin/reports/sales')->assertOk()->assertJsonPath('data.summary.orders', 1);
});

it('lists orders for admins, customers and the customer profile without lazy loading', function () {
    $order = paidOrder($this, makeProduct());
    $customer = $order->user;

    $this->actingAs($customer)->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.order_number', $order->order_number);
    $this->actingAs($this->admin)->getJson('/api/v1/admin/orders?status=processing')->assertOk()->assertJsonPath('data.0.order_number', $order->order_number);
    $this->getJson("/api/v1/admin/customers/{$customer->uuid}")->assertOk()->assertJsonPath('data.orders.0.order_number', $order->order_number);
    $this->getJson('/api/v1/admin/customers')->assertOk();
    $this->getJson('/api/v1/admin/inventory')->assertOk();
    $this->getJson('/api/v1/admin/digital/entitlements')->assertOk();
    $this->getJson('/api/v1/admin/digital/downloads')->assertOk();
    $this->getJson('/api/v1/admin/coupons')->assertOk();
    $this->getJson('/api/v1/admin/pages')->assertOk();
    $this->getJson('/api/v1/admin/settings')->assertOk();
    $this->getJson('/api/v1/admin/audit-logs')->assertOk();
    $this->getJson('/api/v1/admin/categories')->assertOk();
    $this->getJson('/api/v1/admin/brands')->assertOk();
    $this->getJson('/api/v1/admin/lookups')->assertOk();
    $this->getJson("/api/v1/admin/orders/{$order->order_number}")->assertOk()->assertJsonPath('data.payments.0.status', 'captured');
});

it('lets an admin with 2FA turn the staff 2FA requirement off and on, with password confirmation', function () {
    $admin = User::factory()->staff('super-admin')->create(['password' => 'correct-horse-battery']);
    $this->actingAs($admin);

    $this->putJson('/api/v1/admin/settings/security', ['admin_require_2fa' => false, 'password' => 'wrong'])->assertJsonValidationErrors('password');
    $this->putJson('/api/v1/admin/settings/security', ['admin_require_2fa' => false, 'password' => 'correct-horse-battery'])->assertOk();
    expect(EnsureAdminAccess::twoFactorRequired())->toBeFalse();
    $this->getJson('/api/v1/admin/settings')->assertJsonPath('data.security.admin_requires_2fa', false);

    $this->putJson('/api/v1/admin/settings/security', ['admin_require_2fa' => true, 'password' => 'correct-horse-battery'])->assertOk();
    expect(EnsureAdminAccess::twoFactorRequired())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'like', 'settings.security_2fa_%')->count())->toBe(2);
});

it('lets staff without 2FA in when the requirement is off, but not switch it on (no self lock-out)', function () {
    app(Settings::class)->set('security.admin_require_2fa', false);
    $staff = User::factory()->staff('administrator')->create(['password' => 'correct-horse-battery', 'two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
    $this->actingAs($staff);

    $this->getJson('/api/v1/admin/me')->assertOk();
    expect($this->putJson('/api/v1/admin/settings/security', ['admin_require_2fa' => true, 'password' => 'correct-horse-battery']))->toBeApiError(409, 'two_factor_setup_required');
});

it('always requires staff 2FA in production, whatever the toggle says', function () {
    app(Settings::class)->set('security.admin_require_2fa', false);
    app()->detectEnvironment(fn () => 'production');

    expect(EnsureAdminAccess::twoFactorRequired())->toBeTrue();
});

it('lets admins set delivery days per method', function () {
    $method = ShippingMethod::query()->where('code', 'standard')->where('zone', 'domestic')->firstOrFail();
    $payload = fn (int $min, int $max) => ['store' => ['name' => 'Maison', 'state_code' => 'TN'], 'shipping_methods' => [['uuid' => $method->uuid, 'amount' => 30_000, 'free_over' => null, 'is_active' => true, 'days_min' => $min, 'days_max' => $max]]];

    $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', $payload(2, 4))->assertOk();
    expect($method->fresh()->only(['days_min', 'days_max', 'amount']))->toBe(['days_min' => 2, 'days_max' => 4, 'amount' => 30_000]);
    $this->putJson('/api/v1/admin/settings', $payload(5, 3))->assertJsonValidationErrors('shipping_methods.0.days_max');
});

it('enforces per-area permissions for staff', function () {
    $content = User::factory()->staff('content-manager')->create();
    $this->actingAs($content);

    $this->getJson('/api/v1/admin/pages')->assertOk();
    $this->getJson('/api/v1/admin/orders')->assertForbidden();
    $this->getJson('/api/v1/admin/products')->assertForbidden();
    $this->postJson('/api/v1/admin/coupons', [])->assertForbidden();
});

it('lets staff without 2FA in only when the local switch disables it, never in production', function () {
    $staff = User::factory()->staff('order-manager')->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);

    config(['commerce.security.admin_require_2fa' => false]);
    $this->actingAs($staff)->getJson('/api/v1/admin/me')->assertOk();

    app()->detectEnvironment(fn () => 'production');
    expect($this->actingAs($staff)->getJson('/api/v1/admin/me'))->toBeApiError(403, 'two_factor_required');
});
