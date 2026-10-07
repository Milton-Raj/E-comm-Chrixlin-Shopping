<?php

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\StoreSetupSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    (new StoreSetupSeeder)->run();
    $this->admin = User::factory()->staff('administrator')->create();
});

function enableShiprocket(): void
{
    config([
        'shipping.shiprocket.email' => 'api-user@example.test',
        'shipping.shiprocket.password' => 'secret',
        'shipping.shiprocket.webhook_token' => 'hook-token',
        'shipping.shiprocket.pickup_location' => 'Warehouse',
    ]);
}

function fakeShiprocket(array $awb = []): void
{
    Http::fake([
        '*/auth/login' => Http::response(['token' => 'sr-token']),
        '*/orders/create/adhoc' => Http::response(['order_id' => 9001, 'shipment_id' => 7001, 'status' => 'NEW']),
        '*/courier/assign/awb' => Http::sequence($awb ?: [Http::response(['awb_assign_status' => 1, 'response' => ['data' => ['awb_code' => 'AWB123', 'courier_name' => 'Delhivery']]])]),
        '*/courier/generate/pickup' => Http::response(['pickup_status' => 1, 'response' => ['pickup_scheduled_date' => '2026-10-09 10:00:00']]),
        '*/settings/company/pickup' => Http::response(['data' => ['shipping_address' => [['pickup_location' => 'Warehouse'], ['pickup_location' => 'Home']]]]),
    ]);
}

function courierHook($test, string $awb, string $status, string $at = '09 10 2026 12:00:00')
{
    return $test->withHeader('x-api-key', 'hook-token')->postJson('/api/webhooks/delivery-updates', [
        'awb' => $awb, 'current_status' => $status, 'shipment_status' => $status, 'current_timestamp' => $at, 'courier_name' => 'Delhivery',
    ]);
}

it('walks staff through processing → packed without a courier, but never past shipped', function () {
    $order = paidOrder($this, makeProduct());
    $this->actingAs($this->admin);
    expect($order->status->value)->toBe('processing');

    $this->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'packed'])
        ->assertOk()->assertJsonPath('data.status', 'packed')->assertJsonPath('data.courier_enabled', false)->assertJsonPath('data.courier_shipments', []);
    expect($this->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'delivered']))->toBeApiError(409, 'shipment_required');
    expect($this->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'shipped'])->status())->toBe(422);
});

it('books the Shiprocket pickup automatically when an order is packed', function () {
    enableShiprocket();
    fakeShiprocket();
    $order = paidOrder($this, makeProduct(price: 150_000), 2);
    $this->actingAs($this->admin);

    $this->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'packed'])
        ->assertOk()
        ->assertJsonPath('message', 'Marked packed. Shiprocket pickup booked with Delhivery (AWB AWB123).')
        ->assertJsonPath('data.status', 'packed')
        ->assertJsonPath('data.courier_shipments.0.status', 'pickup_scheduled')
        ->assertJsonPath('data.courier_shipments.0.tracking_number', 'AWB123')
        ->assertJsonPath('data.courier_shipments.0.tracking_url', 'https://shiprocket.co/tracking/AWB123');

    Http::assertSent(function (Request $request) use ($order) {
        if (! str_ends_with($request->url(), '/orders/create/adhoc')) {
            return false;
        }

        return $request['order_id'] === $order->order_number && $request['payment_method'] === 'Prepaid'
            && $request['pickup_location'] === 'Warehouse' && $request['billing_state'] === 'Tamil Nadu'
            && $request['order_items'][0]['units'] === 2 && (float) $request['order_items'][0]['selling_price'] === 1500.0
            && $request->hasHeader('Authorization', 'Bearer sr-token');
    });
    $this->assertDatabaseHas('audit_logs', ['action' => 'order.courier_booked']);
});

it('keeps the order packed when booking fails, and resumes on retry without duplicating the Shiprocket order', function () {
    enableShiprocket();
    fakeShiprocket([
        Http::response(['awb_assign_status' => 0, 'response' => ['data' => ['awb_assign_error' => 'Pincode not serviceable']]]),
        Http::response(['awb_assign_status' => 1, 'response' => ['data' => ['awb_code' => 'AWB777', 'courier_name' => 'Blue Dart']]]),
    ]);
    $order = paidOrder($this, makeProduct());
    $this->actingAs($this->admin);

    $this->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'packed'])
        ->assertOk()->assertJsonPath('data.status', 'packed')
        ->assertJsonPath('data.courier_shipments.0.status', 'failed')
        ->assertJsonPath('data.courier_shipments.0.last_error', 'Shiprocket: Pincode not serviceable');

    $this->postJson("/api/v1/admin/orders/{$order->order_number}/courier-booking")
        ->assertOk()->assertJsonPath('data.courier_shipments.0.tracking_number', 'AWB777')->assertJsonPath('data.courier_shipments.0.status', 'pickup_scheduled');

    Http::assertSentCount(5); // login, create, awb (refused), awb, pickup
    expect(collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/orders/create/adhoc')))->toHaveCount(1);
    expect(Shipment::query()->count())->toBe(1);
});

it('moves the order forward from Shiprocket tracking webhooks, once, and never backwards', function () {
    enableShiprocket();
    fakeShiprocket();
    $order = paidOrder($this, makeProduct());
    $this->actingAs($this->admin)->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'packed'])->assertOk();

    $this->withHeader('x-api-key', 'wrong')->postJson('/api/webhooks/delivery-updates', ['awb' => 'AWB123', 'current_status' => 'DELIVERED'])->assertStatus(401);

    courierHook($this, 'AWB123', 'PICKED UP')->assertOk()->assertJsonPath('status', 'processed');
    courierHook($this, 'AWB123', 'PICKED UP')->assertOk()->assertJsonPath('status', 'duplicate');
    expect($order->fresh()->status->value)->toBe('shipped');

    courierHook($this, 'AWB123', 'OUT FOR DELIVERY', '10 10 2026 09:00:00')->assertOk();
    courierHook($this, 'AWB123', 'DELIVERED', '10 10 2026 15:00:00')->assertOk();
    courierHook($this, 'AWB123', 'IN TRANSIT', '10 10 2026 16:00:00')->assertOk();

    $fresh = $order->fresh(['history', 'shipments']);
    expect($fresh->status->value)->toBe('delivered')
        ->and($fresh->fulfillment_status->value)->toBe('fulfilled')
        ->and($fresh->shipments->first()->delivered_at)->not->toBeNull()
        ->and($fresh->history->pluck('to_status')->all())->toContain('shipped', 'out_for_delivery', 'delivered')
        ->and($fresh->history->where('actor_type', 'courier')->count())->toBe(3);

    courierHook($this, 'UNKNOWN', 'DELIVERED')->assertOk()->assertJsonPath('status', 'ignored');
});

it('flags the order for attention on return-to-origin or loss', function () {
    enableShiprocket();
    fakeShiprocket();
    $order = paidOrder($this, makeProduct());
    $this->actingAs($this->admin)->postJson("/api/v1/admin/orders/{$order->order_number}/status", ['to' => 'packed'])->assertOk();

    courierHook($this, 'AWB123', 'RTO INITIATED')->assertOk();

    $fresh = Order::query()->findOrFail($order->id);
    expect($fresh->requires_attention)->toBeTrue()->and($fresh->status->value)->toBe('packed');
    expect(Shipment::query()->first()->status)->toBe('exception');
});

it('shows integration status and tests the Shiprocket connection', function () {
    $this->actingAs($this->admin);
    $this->getJson('/api/v1/admin/settings')
        ->assertJsonPath('data.integrations.shiprocket.configured', false)
        ->assertJsonPath('data.integrations.shiprocket.webhook_url', url('/api/webhooks/delivery-updates'))
        ->assertJsonPath('data.integrations.razorpay.webhook_url', url('/api/webhooks/payment/razorpay'));
    expect($this->postJson('/api/v1/admin/settings/shiprocket/test'))->toBeApiError(409, 'courier_not_configured');

    enableShiprocket();
    fakeShiprocket();
    $this->postJson('/api/v1/admin/settings/shiprocket/test')
        ->assertOk()->assertJsonPath('data.pickup_locations', ['Warehouse', 'Home'])->assertJsonPath('data.pickup_location_found', true);
});
