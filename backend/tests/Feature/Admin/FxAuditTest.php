<?php

use App\Models\AuditLog;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

/** A US shopper buys 2 × ₹1,180 at the current USD rate and pays (test gateway). */
function usPurchase($test): Order
{
    usBag($test);
    $placed = $test->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/checkout/place-order', ['gateway' => 'test'])->assertCreated();
    $test->withHeaders(['Idempotency-Key' => (string) Str::uuid(), 'X-Order-Token' => $placed->json('data.access_token')])
        ->postJson('/api/v1/orders/'.$placed->json('data.order_number').'/payment/confirm', ['outcome' => 'success'])->assertOk();

    return Order::query()->where('order_number', $placed->json('data.order_number'))->firstOrFail();
}

function setUsdRate(string $rate): void
{
    ExchangeRate::query()->updateOrCreate(['base_currency' => 'INR', 'currency' => 'USD'], ['rate' => $rate, 'source' => 'test', 'fetched_at' => now()]);
}

it('keeps every fetched rate instead of overwriting yesterday\'s', function () {
    Http::fake(['open.er-api.com/*' => Http::sequence()
        ->push(['result' => 'success', 'rates' => ['USD' => 0.012]])
        ->push(['result' => 'success', 'rates' => ['USD' => 0.0125]])]);

    Carbon::setTestNow('2026-11-03 00:00:00');
    $this->artisan('app:exchange-rates')->assertSuccessful();
    Carbon::setTestNow('2026-11-03 12:00:00');
    $this->artisan('app:exchange-rates')->assertSuccessful();

    expect(ExchangeRate::query()->where('currency', 'USD')->count())->toBe(1)
        ->and(ExchangeRate::query()->where('currency', 'USD')->value('rate'))->toStartWith('0.0125')
        ->and(ExchangeRateHistory::query()->where('currency', 'USD')->orderBy('fetched_at')->pluck('rate')->map(fn ($r) => (float) $r)->all())->toBe([0.012, 0.0125]);

    $finance = User::factory()->staff('finance-manager')->create();
    $this->actingAs($finance)->getJson('/api/v1/admin/exchange-rates/history?currency=USD&from=2026-11-01&to=2026-11-30')->assertOk()
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.rate', '0.0125') // newest first
        ->assertJsonPath('data.items.1.rate', '0.012');
    Carbon::setTestNow();
});

it('records the market rate, markup and rate time behind each order\'s locked rate', function () {
    sellAbroad(['USD' => '0.012'], markupBps: 250);
    $order = usPurchase($this);

    expect((float) $order->market_rate)->toBe(0.012)
        ->and($order->exchange_markup_bps)->toBe(250)
        ->and((float) $order->exchange_rate)->toBe(0.0123)
        ->and($order->exchange_rate_fetched_at)->not->toBeNull();
});

it('accounts a morning and an evening sale at their own rates in the month-end report', function () {
    sellAbroad(['USD' => '0.012']);
    Carbon::setTestNow('2026-11-10 04:00:00');            // 09:30 in India
    $morning = usPurchase($this);                          // $58.32 for ₹4,860
    Carbon::setTestNow('2026-11-10 13:00:00');            // 18:30 in India
    setUsdRate('0.0125');
    $evening = usPurchase($this);                          // $60.75 for the same ₹4,860
    Carbon::setTestNow('2026-11-10 14:00:00');
    $domestic = paidOrder($this, makeProduct(price: 50_000));
    Carbon::setTestNow();

    expect($morning->presentment_total)->toBe(5_832)->and($evening->presentment_total)->toBe(6_075)
        ->and($morning->grand_total)->toBe($evening->grand_total);

    $finance = User::factory()->staff('finance-manager')->create();
    $report = $this->actingAs($finance)->getJson('/api/v1/admin/reports/international?from=2026-11-01&to=2026-11-30')->assertOk()
        ->assertJsonPath('data.by_currency.0.currency', 'INR')
        ->assertJsonPath('data.by_currency.0.booked.amount', $domestic->grand_total)
        ->assertJsonPath('data.by_currency.1.currency', 'USD')
        ->assertJsonPath('data.by_currency.1.orders', 2)
        ->assertJsonPath('data.by_currency.1.charged.amount', 11_907)       // $58.32 + $60.75
        ->assertJsonPath('data.by_currency.1.booked.amount', 972_000)       // 2 × ₹4,860 in the books
        ->assertJsonPath('data.by_currency.1.average_rate', '0.01225')      // $119.07 ÷ ₹9,720
        ->assertJsonCount(2, 'data.orders')
        ->assertJsonPath('data.orders.0.applied_rate', '0.012')
        ->assertJsonPath('data.orders.0.charged.amount', 5_832)
        ->assertJsonPath('data.orders.1.applied_rate', '0.0125')
        ->assertJsonPath('data.orders.1.charged.amount', 6_075);
    expect($report->json('data.orders.0.payment_reference'))->toStartWith('test_pay_');
});

it('counts refunds in the month they were made, in both currencies', function () {
    sellAbroad(['USD' => '0.012']);
    Carbon::setTestNow('2026-10-30 06:00:00');
    $order = usPurchase($this);
    Carbon::setTestNow('2026-11-02 06:00:00');
    $this->actingAs(User::factory()->staff('administrator')->create())->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson("/api/v1/admin/orders/{$order->order_number}/refunds", ['amount' => 100_000, 'reason' => 'Goodwill', 'restock' => false])->assertOk();
    Carbon::setTestNow();

    // A fresh session: the refund's admin activity was stamped at the frozen clock above.
    $this->flushSession();
    $finance = User::factory()->staff('finance-manager')->create();
    $this->actingAs($finance)->getJson('/api/v1/admin/reports/international?from=2026-10-01&to=2026-10-31')
        ->assertJsonPath('data.by_currency.0.currency', 'USD')->assertJsonPath('data.by_currency.0.orders', 1)->assertJsonPath('data.by_currency.0.refunds', 0);
    $this->getJson('/api/v1/admin/reports/international?from=2026-11-01&to=2026-11-30')
        ->assertJsonPath('data.by_currency.0.currency', 'USD')
        ->assertJsonPath('data.by_currency.0.orders', 0)
        ->assertJsonPath('data.by_currency.0.refunds', 1)
        ->assertJsonPath('data.by_currency.0.refunded_charged.amount', 1_200)
        ->assertJsonPath('data.by_currency.0.refunded_booked.amount', 100_000)
        ->assertJsonPath('data.by_currency.0.net_charged.amount', -1_200);
});

it('uses India time for month boundaries', function () {
    sellAbroad(['USD' => '0.012']);
    Carbon::setTestNow('2026-10-31 19:00:00'); // 1 Nov, 00:30 in India
    usPurchase($this);
    Carbon::setTestNow();

    $finance = User::factory()->staff('finance-manager')->create();
    $this->actingAs($finance)->getJson('/api/v1/admin/reports/international?from=2026-11-01&to=2026-11-30')->assertJsonCount(1, 'data.orders');
    $this->getJson('/api/v1/admin/reports/international?from=2026-10-01&to=2026-10-31')->assertJsonCount(0, 'data.orders');
});

it('limits the FX report and history to finance permission, and exports an audited workbook', function () {
    sellAbroad(['USD' => '0.012']);
    usPurchase($this);

    $orders = User::factory()->staff('order-manager')->create();
    $this->actingAs($orders)->getJson('/api/v1/admin/reports/international')->assertForbidden();
    $this->actingAs($orders)->getJson('/api/v1/admin/exchange-rates/history?currency=USD')->assertForbidden();
    $this->actingAs($orders)->get('/api/v1/admin/exports/international')->assertForbidden();

    $finance = User::factory()->staff('finance-manager')->create();
    $response = $this->actingAs($finance)->get('/api/v1/admin/exports/international?from=2026-11-01&to=2026-11-30')->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('-international-2026-11-01_to_2026-11-30.xlsx')
        ->and(AuditLog::query()->where('action', 'export.downloaded')->where('after->type', 'international')->exists())->toBeTrue();

    // The full store report includes the FX sheets for finance staff.
    $this->actingAs($finance)->get('/api/v1/admin/exports/report')->assertOk();
});

it('reports a whole financial year month by month, with each month\'s rate range', function () {
    sellAbroad(['USD' => '0.012']);
    Carbon::setTestNow('2026-05-10 06:00:00');
    ExchangeRateHistory::query()->create(['base_currency' => 'INR', 'currency' => 'USD', 'rate' => '0.012', 'source' => 'test', 'fetched_at' => now()]);
    usPurchase($this);
    Carbon::setTestNow('2026-06-10 06:00:00');
    setUsdRate('0.0125');
    ExchangeRateHistory::query()->create(['base_currency' => 'INR', 'currency' => 'USD', 'rate' => '0.0125', 'source' => 'test', 'fetched_at' => now()]);
    usPurchase($this);
    Carbon::setTestNow();

    $this->flushSession();
    $finance = User::factory()->staff('finance-manager')->create();
    $this->actingAs($finance)->getJson('/api/v1/admin/reports/international?from=2026-04-01&to=2027-03-31')->assertOk()
        ->assertJsonPath('data.range', ['from' => '2026-04-01', 'to' => '2027-03-31'])
        ->assertJsonPath('data.by_currency.0.orders', 2)
        ->assertJsonPath('data.by_currency.0.booked.amount', 972_000)
        ->assertJsonCount(2, 'data.by_month')
        ->assertJsonPath('data.by_month.0.month', '2026-05')
        ->assertJsonPath('data.by_month.0.charged.amount', 5_832)
        ->assertJsonPath('data.by_month.0.rate_low', '0.012')
        ->assertJsonPath('data.by_month.1.month', '2026-06')
        ->assertJsonPath('data.by_month.1.charged.amount', 6_075)
        ->assertJsonPath('data.by_month.1.rate_high', '0.0125');

    $book = workbook($this->get('/api/v1/admin/exports/international?from=2026-04-01&to=2027-03-31')->assertOk());
    expect($book->getSheetNames())->toBe(['FX by currency', 'FX by month', 'FX orders', 'FX refunds', 'Rate history', 'All rate fetches'])
        ->and($book->getSheetByName('All rate fetches')->getHighestDataRow())->toBeGreaterThanOrEqual(6); // 2 fetches + headings

    expect($this->getJson('/api/v1/admin/reports/international?from=2024-01-01&to=2026-12-31'))->toBeApiError(422);
});
