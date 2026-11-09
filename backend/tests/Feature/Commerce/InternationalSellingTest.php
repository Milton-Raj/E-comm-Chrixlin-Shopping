<?php

use App\Domain\Cart\CartPricer;
use App\Domain\Invoices\InvoiceDocument;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Notifications\AbandonedCartNotification;
use App\Notifications\OrderPaidNotification;
use App\Support\Settings\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

it('sells only to the store country while international selling is off', function () {
    makeProduct();
    $this->getJson('/api/v1/localization')->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.countries', ['IN'])
        ->assertJsonPath('data.default_currency', 'INR');

    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct())->uuid, 'quantity' => 1]);
    expect($this->putJson('/api/v1/cart/currency', ['currency' => 'USD']))->toBeApiError(422, 'currency_unavailable')
        ->and($this->putJson('/api/v1/checkout/address', usAddress()))->toBeApiError(422, 'country_unavailable');
    $this->getJson('/api/v1/cart')->assertJsonPath('data.currency', 'INR');
});

it('tells visitors abroad that delivery is not available yet, with the owner\'s message', function () {
    config(['localization.geoip.fake_country' => 'US']);
    app(Settings::class)->set('international.unavailable_message', 'Coming to the USA soon!');

    $this->getJson('/api/v1/localization')->assertOk()
        ->assertJsonPath('data.detected_country', 'US')
        ->assertJsonPath('data.delivers_to_visitor', false)
        ->assertJsonPath('data.unavailable_message', 'Coming to the USA soon!');
});

it('picks the visitor\'s own currency, or the fallback when it is not offered', function () {
    sellAbroad();

    foreach (['DE' => 'EUR', 'US' => 'USD', 'JP' => 'USD', 'IN' => 'INR'] as $country => $currency) {
        config(['localization.geoip.fake_country' => $country]);
        $this->getJson('/api/v1/localization')->assertJsonPath('data.default_currency', $currency)->assertJsonPath('data.delivers_to_visitor', true);
    }
    $this->getJson('/api/v1/localization')->assertJsonPath('data.currencies.1.code', 'USD')->assertJsonPath('data.currencies.1.rate', '0.012');
});

it('prices the bag in the shopper\'s currency and charges exactly what was shown', function () {
    sellAbroad();
    usBag($this);

    $cart = $this->getJson('/api/v1/cart')->assertOk()
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.items.0.unit_price.amount', 1_416)   // ₹1,180 → $14.16
        ->assertJsonPath('data.items.0.line_total.amount', 2_832)
        ->assertJsonPath('data.totals.shipping.amount', 3_000)      // international standard ₹2,500 → $30
        ->assertJsonPath('data.totals.total.amount', 5_832)
        ->assertJsonPath('data.totals.total.currency', 'USD')
        ->assertJsonPath('data.tax_breakdown.0.code', 'IGST');
    expect($cart->json('data.shipping_method.name'))->toBe('International standard');

    $placed = $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout/place-order', ['gateway' => 'test', 'expected_total' => 5_832])->assertCreated()
        ->assertJsonPath('data.payment.client_payload.amount', 5_832)
        ->assertJsonPath('data.payment.client_payload.currency', 'USD');
    $number = $placed->json('data.order_number');

    $order = Order::query()->where('order_number', $number)->firstOrFail();
    expect($order->currency)->toBe('INR')
        ->and($order->grand_total)->toBe(486_000)                 // books stay in rupees
        ->and($order->presentment_currency)->toBe('USD')
        ->and((float) $order->exchange_rate)->toBe(0.012)
        ->and($order->presentment_total)->toBe(5_832)
        ->and($order->payments()->first()->only(['amount', 'currency']))->toBe(['amount' => 5_832, 'currency' => 'USD']);

    // The test gateway reports the USD amount it was asked for; it matches the payment, so it captures.
    $this->withHeaders(['Idempotency-Key' => (string) Str::uuid(), 'X-Order-Token' => $placed->json('data.access_token')])->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success'])
        ->assertOk()->assertJsonPath('data.payment_status', 'captured');
});

it('rejects a total confirmed in the wrong currency', function () {
    sellAbroad();
    usBag($this);

    expect($this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout/place-order', ['gateway' => 'test', 'expected_total' => 486_000]))->toBeApiError(409, 'price_changed');
});

it('adds the owner\'s markup to the market rate', function () {
    sellAbroad(['USD' => '0.012'], markupBps: 250);
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct(price: 118_000))->uuid, 'quantity' => 1]);
    $this->putJson('/api/v1/cart/currency', ['currency' => 'USD'])
        ->assertJsonPath('data.exchange_rate', '0.0123')
        ->assertJsonPath('data.items.0.unit_price.amount', 1_451); // $14.514 → $14.51
});

it('falls back to rupees for a currency with no stored rate', function () {
    sellAbroad();
    app(Settings::class)->set('international.currencies', ['USD', 'GBP']); // GBP has no rate yet
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct(price: 118_000))->uuid, 'quantity' => 1]);

    $this->putJson('/api/v1/cart/currency', ['currency' => 'GBP'])->assertOk()->assertJsonPath('data.currency', 'INR');
    expect(collect($this->getJson('/api/v1/localization')->json('data.currencies'))->pluck('code')->all())->toBe(['INR', 'USD']);
});

it('refuses an order to an address abroad once international selling is switched off', function () {
    sellAbroad();
    usBag($this);
    app(Settings::class)->set('international.enabled', false);

    expect($this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/checkout/place-order', ['gateway' => 'test']))->toBeApiError(422, 'country_unavailable');
});

it('shows customers their own currency and staff the rupee books', function () {
    sellAbroad();
    $customer = User::factory()->customer()->create();
    $this->actingAs($customer);
    usBag($this);
    $number = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test'])->json('data.order_number');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success']);

    $this->getJson("/api/v1/orders/{$number}")->assertOk()
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.totals.subtotal.amount', 2_832)
        ->assertJsonPath('data.totals.total.amount', 5_832)
        ->assertJsonPath('data.items.0.unit_price.amount', 1_416);

    $this->actingAs(User::factory()->staff('administrator')->create())
        ->getJson("/api/v1/admin/orders/{$number}")->assertOk()
        ->assertJsonPath('data.currency', 'INR')
        ->assertJsonPath('data.totals.total.amount', 486_000)
        ->assertJsonPath('data.charged.amount.amount', 5_832)
        ->assertJsonPath('data.charged.amount.currency', 'USD')
        ->assertJsonPath('data.payments.0.amount.currency', 'USD')
        ->assertJsonPath('data.courier_enabled', false); // Shiprocket books domestic shipments only
});

it('refunds the same share of the foreign charge, and exactly the rest at the end', function () {
    sellAbroad();
    usBag($this);
    $placed = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test']);
    $number = $placed->json('data.order_number');
    $this->withHeaders(['Idempotency-Key' => (string) Str::uuid(), 'X-Order-Token' => $placed->json('data.access_token')])
        ->postJson("/api/v1/orders/{$number}/payment/confirm", ['outcome' => 'success'])->assertOk();

    $admin = User::factory()->staff('administrator')->create();
    $this->actingAs($admin)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/admin/orders/{$number}/refunds", ['amount' => 100_000, 'reason' => 'Goodwill', 'restock' => false])
        ->assertOk()->assertJsonPath('data.refunds.0.gateway_amount.amount', 1_200)->assertJsonPath('data.refunds.0.gateway_amount.currency', 'USD');
    $this->actingAs($admin)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/admin/orders/{$number}/refunds", ['amount' => 386_000, 'reason' => 'Returned', 'restock' => false])
        ->assertOk()->assertJsonPath('data.payment_status', 'refunded');

    $order = Order::query()->where('order_number', $number)->firstOrFail();
    expect($order->refunds()->pluck('gateway_amount')->all())->toBe([1_200, 4_632])
        ->and(PaymentTransaction::query()->where('type', 'refund')->pluck('currency')->unique()->all())->toBe(['USD'])
        ->and((int) PaymentTransaction::query()->where('type', 'refund')->sum('amount'))->toBe(5_832);
});

it('lets settings managers switch international selling, audited and validated', function () {
    $content = User::factory()->staff('content-manager')->create();
    $this->actingAs($content)->getJson('/api/v1/admin/settings/international')->assertForbidden();

    $admin = User::factory()->staff('administrator')->create();
    Http::fake(['open.er-api.com/*' => Http::response(['result' => 'success', 'rates' => ['USD' => 0.01198, 'EUR' => 0.01102, 'XYZ' => 3]])]);
    $body = ['enabled' => true, 'currencies' => ['USD', 'EUR'], 'fallback_currency' => 'USD', 'markup_bps' => 200, 'unavailable_message' => 'Soon!'];

    expect($this->actingAs($admin)->putJson('/api/v1/admin/settings/international', [...$body, 'fallback_currency' => 'GBP']))->toBeApiError(422);
    $this->actingAs($admin)->putJson('/api/v1/admin/settings/international', $body)->assertOk()
        ->assertJsonPath('data.settings.enabled', true)
        ->assertJsonPath('data.settings.markup_bps', 200)
        ->assertJsonPath('data.currencies.0.code', 'USD')
        ->assertJsonPath('data.currencies.0.market_rate', '0.01198')
        ->assertJsonPath('data.currencies.0.rate', '0.0122196'); // + 2%

    expect(AuditLog::query()->where('action', 'settings.international_updated')->exists())->toBeTrue()
        ->and(ExchangeRate::query()->where('currency', 'XYZ')->exists())->toBeFalse(); // only offerable currencies are stored
});

it('refreshes rates on demand and reports a provider failure', function () {
    $admin = User::factory()->staff('administrator')->create();
    Http::fake(['open.er-api.com/*' => Http::sequence()
        ->push(['result' => 'success', 'rates' => ['USD' => 0.012]])
        ->push(['result' => 'error'], 500)]);

    $this->actingAs($admin)->postJson('/api/v1/admin/settings/international/rates')->assertOk()->assertJsonPath('message', 'Updated 1 exchange rates.');
    expect($this->actingAs($admin)->postJson('/api/v1/admin/settings/international/rates'))->toBeApiError(502, 'rates_unavailable')
        ->and(ExchangeRate::query()->where('currency', 'USD')->value('rate'))->toStartWith('0.012');

    $this->artisan('app:exchange-rates')->assertFailed();
});

it('shows Indian GST in the customer\'s currency on the invoice and the confirmation email, explained', function () {
    sellAbroad();
    usBag($this);
    $placed = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test']);
    $this->withHeaders(['Idempotency-Key' => (string) Str::uuid(), 'X-Order-Token' => $placed->json('data.access_token')])
        ->postJson('/api/v1/orders/'.$placed->json('data.order_number').'/payment/confirm', ['outcome' => 'success'])->assertOk();
    $order = Order::query()->where('order_number', $placed->json('data.order_number'))->with('invoice')->firstOrFail();

    // Invoice: rupee GST stays the legal value; the USD section repeats it in the customer's currency.
    $html = view('invoices.tax-invoice', app(InvoiceDocument::class)->data($order->invoice))->render();
    expect($html)->toContain('In USD — the currency you paid')
        ->toContain('$58.32')                 // total paid
        ->toContain('$4.32')                  // ₹360 IGST at 0.012
        ->toContain('charged under Indian GST law')
        ->toContain('₹360.00');               // the rupee GST is still there

    // Email: total and GST in USD, with the reason.
    Notification::assertSentOnDemand(OrderPaidNotification::class, function (OrderPaidNotification $n) {
        $text = implode("\n", $n->toMail(new AnonymousNotifiable)->introLines);

        return str_contains($text, 'Total paid: $58.32') && str_contains($text, 'This includes $4.32 Indian GST (IGST)') && str_contains($text, 'under Indian law');
    });
});

it('sends the abandoned-bag reminder in the shopper\'s currency', function () {
    sellAbroad();
    $this->postJson('/api/v1/cart/items', ['variant_uuid' => variantOf(makeProduct(price: 118_000))->uuid, 'quantity' => 2]);
    $this->putJson('/api/v1/cart/currency', ['currency' => 'USD']);
    $cart = Cart::query()->latest('id')->firstOrFail();

    $mail = (new AbandonedCartNotification(app(CartPricer::class)->price($cart), false))->toMail(new AnonymousNotifiable);
    expect(implode("\n", $mail->introLines))->toContain('Bag total: $28.32');
});

it('guesses the country from the browser region only when the IP gives none', function () {
    sellAbroad();

    $this->getJson('/api/v1/localization?hint=de')->assertOk()
        ->assertJsonPath('data.detected_country', 'DE')->assertJsonPath('data.detected_by', 'browser')->assertJsonPath('data.default_currency', 'EUR');
    $this->getJson('/api/v1/localization?hint=ZZ')->assertJsonPath('data.detected_country', null);
    $this->getJson('/api/v1/localization?hint=<script>')->assertJsonPath('data.detected_country', null);

    config(['localization.geoip.fake_country' => 'US']); // the IP answer wins over the browser
    $this->getJson('/api/v1/localization?hint=DE')->assertJsonPath('data.detected_country', 'US')->assertJsonPath('data.detected_by', 'ip');
});

it('installs the IP country database from the admin and explains a failure', function () {
    config(['localization.geoip.path' => storage_path('framework/testing/geoip-'.Str::random(6).'.mmdb')]);
    Http::fake(['download.db-ip.com/*' => Http::response('Forbidden', 403)]);

    $this->actingAs(User::factory()->staff('content-manager')->create())->postJson('/api/v1/admin/settings/international/geoip')->assertForbidden();
    expect($this->actingAs(User::factory()->staff('administrator')->create())->postJson('/api/v1/admin/settings/international/geoip'))
        ->toBeApiError(502, 'geoip_unavailable')
        ->and($this->postJson('/api/v1/admin/settings/international/geoip')->json('message'))->toContain('answered HTTP 403');
    $this->artisan('app:geoip-update')->expectsOutputToContain('answered HTTP 403')->assertFailed();
});
