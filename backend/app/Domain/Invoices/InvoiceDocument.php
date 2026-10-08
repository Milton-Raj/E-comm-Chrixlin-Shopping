<?php

namespace App\Domain\Invoices;

use App\Models\Invoice;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Support\Money\Money;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;

/**
 * Renders a GST tax invoice as PDF. All figures come from the order snapshot, so the
 * invoice always matches what the customer paid. Prices are GST-inclusive; the invoice
 * shows the taxable value and the CGST+SGST (same state) or IGST (other state) split.
 */
class InvoiceDocument
{
    public function pdf(Invoice $invoice): string
    {
        $dompdf = new Dompdf((new Options)
            ->setDefaultFont('DejaVu Sans')
            ->setIsRemoteEnabled(false)   // never fetch remote resources while rendering
            ->setIsPhpEnabled(false)
            ->setChroot(resource_path('views/invoices')));
        $dompdf->loadHtml(view('invoices.tax-invoice', $this->data($invoice))->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function filename(Invoice $invoice): string
    {
        return 'Invoice-'.str_replace('/', '-', $invoice->invoice_number).'.pdf';
    }

    /** @return array<string, mixed> */
    public function data(Invoice $invoice): array
    {
        $order = $invoice->order->loadMissing(['items', 'addresses', 'latestPayment']);
        $currency = $order->currency;
        $breakdown = $order->tax_breakdown ?? [];
        $interState = array_key_exists('IGST', $breakdown);
        $fmt = fn (int $minor) => $this->money($minor, $currency);

        $rows = [];
        $lineTax = 0;
        foreach ($order->items as $i => $item) {
            /** @var OrderItem $item */
            $taxable = $item->line_total - $item->tax_total;
            $lineTax += $item->tax_total;
            $rows[] = $this->row($i + 1, $item->name, trim(($item->variant_name ? $item->variant_name.' · ' : '').'SKU '.$item->sku),
                $item->quantity, $item->unit_price, $taxable, $item->tax_total, $item->line_total, $item->tax_rate_bps, $interState, $fmt);
        }

        $deliveryTax = max(0, $order->tax_total - $lineTax);
        if ($order->shipping_total > 0) {
            $rows[] = $this->row(count($rows) + 1, 'Delivery charges', (string) data_get($order->shipping_method, 'name', ''), 1,
                $order->shipping_total, $order->shipping_total - $deliveryTax, $deliveryTax, $order->shipping_total, null, $interState, $fmt);
        }

        $billing = $order->addresses->firstWhere('type', 'billing') ?? $order->shippingAddress();
        $shipping = $order->shippingAddress();
        $seller = $invoice->seller;
        $placeState = $shipping->state_code ?? $billing->state_code ?? $seller['state_code'];
        $payment = $order->latestPayment;

        return [
            'invoice' => $invoice,
            'order' => $order,
            'seller' => $seller,
            'sellerState' => $this->stateName('IN', $seller['state_code']),
            'billTo' => $this->address($billing, $order->email, $order->phone),
            'shipTo' => $shipping ? $this->address($shipping, null, null) : null,
            'placeOfSupply' => $this->supplyState($shipping->country_code ?? $billing->country_code ?? 'IN', $placeState),
            'interState' => $interState,
            'rows' => $rows,
            'totals' => [
                'subtotal' => $fmt((int) $order->items->sum('line_subtotal')),
                'discount' => $order->discount_total > 0 ? $fmt($order->discount_total) : null,
                'coupon' => $order->coupon_code,
                'taxable' => $fmt($order->grand_total - $order->tax_total),
                'taxes' => collect($breakdown)->map(fn (int $amount, string $code) => ['code' => $code, 'amount' => $fmt($amount)])->values()->all(),
                'tax' => $fmt($order->tax_total),
                'total' => $fmt($order->grand_total),
                'refunded' => $order->refunded_total > 0 ? $fmt($order->refunded_total) : null,
            ],
            'amountInWords' => $currency === 'INR' ? AmountInWords::rupees($order->grand_total) : null,
            'paidWith' => $payment ? trim(Str::headline($payment->provider).($payment->method ? ' · '.Str::upper($payment->method) : '')) : null,
            'paymentReference' => $payment?->provider_payment_id,
            'paidAt' => $order->paid_at?->copy()->setTimezone((string) config('commerce.store.timezone', 'UTC')),
            'issuedAt' => $invoice->issued_at->copy()->setTimezone((string) config('commerce.store.timezone', 'UTC')),
        ];
    }

    /**
     * @param  callable(int): string  $fmt
     * @return array<string, mixed>
     */
    private function row(int $n, string $name, string $detail, int $qty, int $unit, int $taxable, int $tax, int $total, ?int $rateBps, bool $interState, callable $fmt): array
    {
        $rateBps ??= $taxable > 0 ? (int) round($tax / $taxable * 10_000 / 50) * 50 : 0; // older orders: nearest 0.5%
        $half = intdiv($tax, 2);

        return [
            'n' => $n, 'name' => $name, 'detail' => $detail, 'qty' => $qty,
            'unit' => $fmt($unit), 'taxable' => $fmt($taxable), 'total' => $fmt($total),
            'rate' => rtrim(rtrim(number_format($rateBps / 100, 2), '0'), '.').'%',
            'half_rate' => rtrim(rtrim(number_format($rateBps / 200, 2), '0'), '.').'%',
            'igst' => $interState ? $fmt($tax) : null,
            'cgst' => $interState ? null : $fmt($half),
            'sgst' => $interState ? null : $fmt($tax - $half),
        ];
    }

    /** @return array{name: string, lines: list<string>} */
    private function address(?OrderAddress $a, ?string $email, ?string $phone): array
    {
        if (! $a) {
            return ['name' => (string) $email, 'lines' => $phone ? ["Phone {$phone}"] : []];
        }

        return [
            'name' => $a->name,
            'lines' => array_values(array_filter([
                $a->line1, $a->line2,
                trim("{$a->city} {$a->postal_code}"),
                $this->stateName($a->country_code, $a->state_code).', '.config("regions.countries.{$a->country_code}", $a->country_code),
                ($a->phone ?: $phone) ? 'Phone '.($a->phone ?: $phone) : null,
                $email,
            ])),
        ];
    }

    private function stateName(string $country, string $code): string
    {
        return (string) config("regions.states.{$country}.{$code}", $code);
    }

    /** "Tamil Nadu (33)" — the place of supply with its GST state code. */
    private function supplyState(string $country, string $code): string
    {
        $gst = $country === 'IN' ? config("regions.gst_state_codes.{$code}") : null;

        return $this->stateName($country, $code).($gst ? " ({$gst})" : '');
    }

    /** Currency with Indian digit grouping for INR (12,34,567.00); other currencies group by thousands. */
    private function money(int $minor, string $currency): string
    {
        $exp = Money::exponent($currency);
        $negative = $minor < 0;
        $abs = abs($minor);
        $whole = (string) intdiv($abs, 10 ** $exp);
        $fraction = $exp ? '.'.str_pad((string) ($abs % (10 ** $exp)), $exp, '0', STR_PAD_LEFT) : '';
        if ($currency === 'INR' && strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $whole = (string) preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
        } else {
            $whole = number_format((float) $whole, 0, '', ',');
        }
        $symbol = (string) config("commerce.currency_symbols.{$currency}", "{$currency} ");

        return ($negative ? '−' : '').$symbol.$whole.$fraction;
    }
}
