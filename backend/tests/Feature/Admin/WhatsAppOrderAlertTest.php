<?php

use App\Jobs\SendWhatsAppOrderAlert;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Notification::fake();
    config(['services.whatsapp.access_token' => 'test-token', 'services.whatsapp.phone_number_id' => '1234567890', 'services.whatsapp.api_version' => 'v23.0']);
});

function whatsappOn(string $number = '919876543210'): void
{
    app(Settings::class)->set('alerts.whatsapp_enabled', true);
    app(Settings::class)->set('alerts.whatsapp_number', $number);
}

it('sends the owner a WhatsApp alert as soon as an order is paid', function () {
    whatsappOn();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    $order = paidOrder($this, makeProduct(price: 118_000), qty: 2);

    Http::assertSent(function (HttpRequest $r) use ($order) {
        $params = array_column($r['template']['components'][0]['parameters'], 'text');

        return $r->url() === 'https://graph.facebook.com/v23.0/1234567890/messages'
            && $r->hasHeader('Authorization', 'Bearer test-token')
            && $r['to'] === '919876543210'
            && $r['template']['name'] === 'new_order_alert'
            && $params === [$order->order_number, '₹2,610.00', '2 items', 'Chennai, Tamil Nadu', 'http://localhost:3000/admin/orders/'.$order->order_number];
    });
    Http::assertSentCount(1);
});

it('never sends customer names, emails or phone numbers to WhatsApp', function () {
    whatsappOn();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
    $order = paidOrder($this, makeProduct());

    Http::assertSent(fn (HttpRequest $r) => ! str_contains(json_encode($r->data()), $order->email) && ! str_contains(json_encode($r->data()), $order->user->name));
});

it('shows a foreign order in the currency paid and in rupees', function () {
    sellAbroad(['USD' => '0.012']);
    usBag($this);
    $placed = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test']);
    $order = Order::query()->where('order_number', $placed->json('data.order_number'))->firstOrFail();

    expect(SendWhatsAppOrderAlert::parameters($order)[1])->toBe('$58.32 (₹4,860.00)')
        ->and(SendWhatsAppOrderAlert::parameters($order)[3])->toBe('New York, United States');
});

it('sends nothing while alerts are off, or when WhatsApp is not connected', function () {
    Http::fake();
    app(Settings::class)->set('alerts.whatsapp_number', '919876543210'); // number saved, switch off
    paidOrder($this, makeProduct());

    whatsappOn();
    config(['services.whatsapp.access_token' => null]);
    paidOrder($this, makeProduct());

    Http::assertNothingSent();
});

it('hands a temporary WhatsApp failure to the queue for retries, but gives up on a bad template', function () {
    // One fake for the whole test: a later Http::fake() would not replace an earlier stub for the same URL.
    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['error' => ['code' => 131000, 'message' => 'Something went wrong']], 500)
        ->push(['error' => ['code' => 132001, 'message' => 'Template name does not exist in the translation']], 404)]);
    $order = paidOrder($this, makeProduct()); // alerts still off: nothing sent
    whatsappOn();
    Queue::fake();

    // Straight after the payment response (Laravel runs it on the sync queue), WhatsApp is down: the alert moves to the real queue.
    $afterResponse = new SendWhatsAppOrderAlert($order);
    $afterResponse->setJob(new SyncJob(app(), '{}', 'sync', 'sync'));
    app()->call([$afterResponse, 'handle']);
    Queue::assertPushedOn('critical', SendWhatsAppOrderAlert::class);

    Queue::fake();
    app()->call([new SendWhatsAppOrderAlert($order), 'handle']);
    Queue::assertNothingPushed();
});

it('lets settings managers save the number, audited, and send a test message', function () {
    $admin = User::factory()->staff('administrator')->create();
    $this->actingAs(User::factory()->staff('order-manager')->create())->getJson('/api/v1/admin/settings/order-alerts')->assertForbidden();

    expect($this->actingAs($admin)->putJson('/api/v1/admin/settings/order-alerts', ['whatsapp_enabled' => true, 'whatsapp_number' => '']))->toBeApiError(422)
        ->and($this->putJson('/api/v1/admin/settings/order-alerts', ['whatsapp_enabled' => true, 'whatsapp_number' => '98765']))->toBeApiError(422);

    $this->putJson('/api/v1/admin/settings/order-alerts', ['whatsapp_enabled' => true, 'whatsapp_number' => '+91 98765 43210'])->assertOk()
        ->assertJsonPath('data.whatsapp_enabled', true)
        ->assertJsonPath('data.whatsapp_number', '+919876543210')
        ->assertJsonPath('data.connected', true);
    expect(AuditLog::query()->where('action', 'settings.order_alerts_updated')->value('after'))->toMatchArray(['whatsapp_number' => '+91••••••3210']);

    Http::fake(['graph.facebook.com/*' => Http::sequence()
        ->push(['messages' => [['id' => 'wamid.test']]])
        ->push(['error' => ['code' => 131030, 'message' => 'Recipient phone number not in allowed list']], 400)]);
    $this->postJson('/api/v1/admin/settings/order-alerts/test')->assertOk()->assertJsonPath('message', 'Test message sent to +91••••••3210. It should arrive within a few seconds.');
    expect($this->postJson('/api/v1/admin/settings/order-alerts/test'))->toBeApiError(422, 'whatsapp_failed');
    $this->assertDatabaseHas('audit_logs', ['action' => 'settings.order_alerts_tested']);
});
