<?php

use App\Domain\Invoices\AmountInWords;
use App\Domain\Invoices\InvoiceDocument;
use App\Domain\Invoices\IssueInvoice;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPaidNotification;
use App\Support\Settings\Settings;
use Database\Seeders\StoreSetupSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();
    (new StoreSetupSeeder)->run();
});

it('issues one GST invoice when payment is captured and emails it as a PDF', function () {
    app(Settings::class)->set('store.legal_name', 'Chrixlin Retail Pvt Ltd');
    app(Settings::class)->set('store.gstin', '33ABCDE1234F1Z5');
    $order = paidOrder($this, makeProduct(price: 118_000), 2);

    $invoice = Invoice::query()->where('order_id', $order->id)->sole();
    expect($invoice->invoice_number)->toBe(IssueInvoice::number(IssueInvoice::financialYear(now()), 1))
        ->and($invoice->seller['gstin'])->toBe('33ABCDE1234F1Z5');

    Notification::assertSentOnDemand(OrderPaidNotification::class, function (OrderPaidNotification $n, array $channels, AnonymousNotifiable $to) use ($order, $invoice) {
        $mail = $n->toMail($to);
        $attachment = $mail->rawAttachments[0] ?? null;

        return $to->routes['mail'] === $order->email
            && $attachment !== null
            && $attachment['name'] === 'Invoice-'.str_replace('/', '-', $invoice->invoice_number).'.pdf'
            && $attachment['options']['mime'] === 'application/pdf'
            && str_starts_with($attachment['data'], '%PDF');
    });
    expect($invoice->fresh()->emailed_at)->not->toBeNull();
});

it('never issues a second invoice or skips a number, even if the payment is confirmed again', function () {
    $first = paidOrder($this, makeProduct());
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/orders/{$first->order_number}/payment/confirm", ['outcome' => 'success']);
    app(IssueInvoice::class)->handle($first);
    $second = paidOrder($this, makeProduct());

    expect(Invoice::query()->where('order_id', $first->id)->count())->toBe(1)
        ->and(Invoice::query()->orderBy('id')->pluck('sequence')->all())->toBe([1, 2])
        ->and($second->invoice()->first()->invoice_number)->toEndWith('/000002');
});

it('numbers invoices by Indian financial year in the store time zone', function () {
    config(['commerce.store.timezone' => 'Asia/Kolkata']);
    expect(IssueInvoice::financialYear(Carbon::parse('2027-03-31 19:00', 'UTC')))->toBe('2027-28') // 1 Apr 2027, 00:30 IST
        ->and(IssueInvoice::financialYear(Carbon::parse('2027-03-31 18:00', 'Asia/Kolkata')))->toBe('2026-27')
        ->and(IssueInvoice::number('2026-27', 42))->toBe('INV26-27/000042')
        ->and(strlen(IssueInvoice::number('2026-27', 999_999)))->toBeLessThanOrEqual(16);
});

it('writes amounts in Indian words', function () {
    expect(AmountInWords::rupees(6_340_000))->toBe('Rupees Sixty Three Thousand Four Hundred Only')
        ->and(AmountInWords::rupees(1_250_000_050))->toBe('Rupees One Crore Twenty Five Lakh and Fifty Paise Only')
        ->and(AmountInWords::rupees(0))->toBe('Rupees Zero Only');
});

it('shows the GST split that adds up to what was paid', function () {
    $order = paidOrder($this, makeProduct(price: 118_000));
    $data = app(InvoiceDocument::class)->data($order->invoice()->first());

    expect($data['interState'])->toBeFalse()
        ->and($data['rows'][0]['rate'])->toBe('18%')
        ->and($data['rows'][0]['half_rate'])->toBe('9%')
        ->and(collect($data['totals']['taxes'])->pluck('code')->all())->toBe(['CGST', 'SGST'])
        ->and($data['placeOfSupply'])->toBe('Tamil Nadu (33)');
});

it('lets only the order owner download the invoice, and only once paid', function () {
    $order = paidOrder($this, makeProduct());

    $this->get("/api/v1/orders/{$order->order_number}/invoice")
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.(new InvoiceDocument)->filename($order->invoice()->first()).'"');

    $this->actingAs(User::factory()->customer()->create());
    expect($this->get("/api/v1/orders/{$order->order_number}/invoice")->status())->toBe(404);
});

it('lets staff download any paid order invoice, issuing one for older orders', function () {
    $order = paidOrder($this, makeProduct());
    Invoice::query()->where('order_id', $order->id)->toBase()->delete(); // simulate an order paid before invoicing existed
    $this->actingAs(User::factory()->staff('administrator')->create());

    $this->get("/api/v1/admin/orders/{$order->order_number}/invoice")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(Invoice::query()->where('order_id', $order->id)->count())->toBe(1);
});

it('keeps invoices as permanent financial records', function () {
    $order = paidOrder($this, makeProduct());
    expect(fn () => $order->invoice()->first()->delete())->toThrow(LogicException::class);
});

it('validates the seller GSTIN against the business state', function () {
    $this->actingAs(User::factory()->staff('administrator')->create());
    $payload = fn (string $gstin) => ['store' => ['name' => 'Maison', 'state_code' => 'TN', 'gstin' => $gstin, 'legal_name' => 'Maison Pvt Ltd', 'address' => "12 Marina Road\nChennai 600001"]];

    $this->putJson('/api/v1/admin/settings', $payload('29ABCDE1234F1Z5'))->assertJsonValidationErrors('store.gstin'); // Karnataka code
    $this->putJson('/api/v1/admin/settings', $payload('NOT-A-GSTIN'))->assertJsonValidationErrors('store.gstin');
    $this->putJson('/api/v1/admin/settings', $payload('33abcde1234f1z5'))->assertOk();
    expect(app(Settings::class)->get('store.gstin'))->toBe('33ABCDE1234F1Z5');
});
