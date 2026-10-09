<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Tax invoice {{ $invoice->invoice_number }}</title>
<style>
  /* dompdf supports a CSS 2.1 subset: tables, not flex/grid. Brand palette (ARCHITECTURE D15). */
  @page { margin: 34px 38px 54px; }
  * { box-sizing: border-box; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5px; color: #0a0908; line-height: 1.45; }
  .brand { font-size: 20px; letter-spacing: 5px; text-transform: uppercase; color: #0a0908; }
  .muted { color: #5e503f; }
  .small { font-size: 8.5px; }
  .title { font-size: 15px; font-weight: bold; color: #49111c; letter-spacing: 1.5px; text-transform: uppercase; text-align: right; }
  .rule { border-top: 2px solid #49111c; margin: 10px 0 14px; }
  table { width: 100%; border-collapse: collapse; }
  .meta td { padding: 2px 0; vertical-align: top; }
  .meta .label { color: #5e503f; width: 46%; }
  .parties td { width: 33.33%; vertical-align: top; padding: 10px 12px; border: 1px solid #ddd6cf; background: #faf8f6; }
  .parties h3 { margin: 0 0 4px; font-size: 7.5px; letter-spacing: 1.5px; text-transform: uppercase; color: #49111c; }
  .parties .name { font-weight: bold; font-size: 10px; }
  .items { margin-top: 16px; }
  .items th { background: #49111c; color: #ffffff; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.6px; padding: 7px 5px; text-align: left; }
  .items td { padding: 7px 5px; border-bottom: 1px solid #e8e2dc; vertical-align: top; }
  .items tr.alt td { background: #f7f5f3; }
  .num { text-align: right; white-space: nowrap; }
  .center { text-align: center; }
  .item-name { font-weight: bold; }
  .totals { width: 48%; margin-left: 52%; margin-top: 12px; }
  .totals td { padding: 4px 6px; }
  .totals .grand td { border-top: 2px solid #49111c; font-size: 12px; font-weight: bold; padding-top: 8px; color: #49111c; }
  .words { margin-top: 12px; padding: 8px 12px; border-left: 3px solid #49111c; background: #faf8f6; }
  .paid { display: inline-block; padding: 3px 10px; border: 1.5px solid #1e6b34; color: #1e6b34; font-weight: bold; letter-spacing: 1.5px; font-size: 9px; text-transform: uppercase; }
  .notes { margin-top: 18px; color: #5e503f; font-size: 8px; }
  .footer { position: fixed; bottom: -36px; left: 0; right: 0; text-align: center; font-size: 7.5px; color: #8a7d6e; }
</style>
</head>
<body>
  <div class="footer">{{ $seller['legal_name'] }} · Tax invoice {{ $invoice->invoice_number }} · This is a computer-generated invoice and needs no signature.</div>

  <table>
    <tr>
      <td style="vertical-align: top;">
        <div class="brand">{{ config('commerce.store.name') }}</div>
        <div class="muted small" style="margin-top: 4px;">{{ $seller['legal_name'] }}</div>
      </td>
      <td style="vertical-align: top; text-align: right;">
        <div class="title">Tax invoice</div>
        <div class="muted small">Original for recipient</div>
      </td>
    </tr>
  </table>
  <div class="rule"></div>

  <table>
    <tr>
      <td style="width: 50%; vertical-align: top;">
        <table class="meta">
          <tr><td class="label">Invoice number</td><td><strong>{{ $invoice->invoice_number }}</strong></td></tr>
          <tr><td class="label">Invoice date</td><td>{{ $issuedAt->format('j M Y') }}</td></tr>
          <tr><td class="label">Order number</td><td>{{ $order->order_number }}</td></tr>
          <tr><td class="label">Order date</td><td>{{ $order->placed_at?->copy()->setTimezone(config('commerce.store.timezone'))->format('j M Y, g:i A') }}</td></tr>
        </table>
      </td>
      <td style="width: 50%; vertical-align: top;">
        <table class="meta">
          <tr><td class="label">Place of supply</td><td>{{ $placeOfSupply }}</td></tr>
          <tr><td class="label">Supply type</td><td>{{ $interState ? 'Inter-state (IGST)' : 'Intra-state (CGST + SGST)' }}</td></tr>
          @if ($paidWith)
            <tr><td class="label">Paid with</td><td>{{ $paidWith }}</td></tr>
          @endif
          @if ($paymentReference)
            <tr><td class="label">Payment reference</td><td>{{ $paymentReference }}</td></tr>
          @endif
        </table>
      </td>
    </tr>
  </table>

  <table class="parties" style="margin-top: 14px; border-collapse: separate; border-spacing: 6px 0;">
    <tr>
      <td>
        <h3>Sold by</h3>
        <div class="name">{{ $seller['legal_name'] }}</div>
        @if ($seller['address'])<div>{!! nl2br(e($seller['address'])) !!}</div>@endif
        <div>{{ $sellerState }}, India</div>
        @if ($seller['gstin'])<div style="margin-top: 4px;"><strong>GSTIN</strong> {{ $seller['gstin'] }}</div>@endif
        @if ($seller['email'])<div class="muted">{{ $seller['email'] }}</div>@endif
      </td>
      <td>
        <h3>Bill to</h3>
        <div class="name">{{ $billTo['name'] }}</div>
        @foreach ($billTo['lines'] as $line)<div>{{ $line }}</div>@endforeach
      </td>
      <td>
        <h3>Ship to</h3>
        @if ($shipTo)
          <div class="name">{{ $shipTo['name'] }}</div>
          @foreach ($shipTo['lines'] as $line)<div>{{ $line }}</div>@endforeach
        @else
          <div class="muted">Digital delivery — no shipping address</div>
        @endif
      </td>
    </tr>
  </table>

  <table class="items">
    <thead>
      <tr>
        <th class="center" style="width: 4%;">#</th>
        <th>Item</th>
        <th class="center" style="width: 5%;">Qty</th>
        <th class="num" style="width: 11%;">Unit price</th>
        <th class="num" style="width: 12%;">Taxable value</th>
        @if ($interState)
          <th class="num" style="width: 14%;">IGST</th>
        @else
          <th class="num" style="width: 11%;">CGST</th>
          <th class="num" style="width: 11%;">SGST</th>
        @endif
        <th class="num" style="width: 12%;">Amount</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($rows as $row)
        <tr class="{{ $loop->even ? 'alt' : '' }}">
          <td class="center">{{ $row['n'] }}</td>
          <td><div class="item-name">{{ $row['name'] }}</div>@if ($row['detail'])<div class="muted small">{{ $row['detail'] }}</div>@endif</td>
          <td class="center">{{ $row['qty'] }}</td>
          <td class="num">{{ $row['unit'] }}</td>
          <td class="num">{{ $row['taxable'] }}</td>
          @if ($interState)
            <td class="num">{{ $row['igst'] }}<div class="muted small">@ {{ $row['rate'] }}</div></td>
          @else
            <td class="num">{{ $row['cgst'] }}<div class="muted small">@ {{ $row['half_rate'] }}</div></td>
            <td class="num">{{ $row['sgst'] }}<div class="muted small">@ {{ $row['half_rate'] }}</div></td>
          @endif
          <td class="num"><strong>{{ $row['total'] }}</strong></td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totals">
    <tr><td class="muted">Items total (incl. GST)</td><td class="num">{{ $totals['subtotal'] }}</td></tr>
    @if ($totals['discount'])
      <tr><td class="muted">Discount{{ $totals['coupon'] ? ' ('.$totals['coupon'].')' : '' }}</td><td class="num">− {{ $totals['discount'] }}</td></tr>
    @endif
    <tr><td class="muted">Total taxable value</td><td class="num">{{ $totals['taxable'] }}</td></tr>
    @foreach ($totals['taxes'] as $tax)
      <tr><td class="muted">{{ $tax['code'] }}</td><td class="num">{{ $tax['amount'] }}</td></tr>
    @endforeach
    <tr class="grand"><td>Total paid</td><td class="num">{{ $totals['total'] }}</td></tr>
    @if ($totals['refunded'])
      <tr><td class="muted">Refunded since</td><td class="num">− {{ $totals['refunded'] }}</td></tr>
    @endif
  </table>

  @if ($amountInWords)
    <div class="words"><span class="muted small">Amount in words</span><br><strong>{{ $amountInWords }}</strong></div>
  @endif

  <div style="margin-top: 14px;">
    <span class="paid">Paid in full</span>
    @if ($paidAt)<span class="muted small" style="margin-left: 8px;">on {{ $paidAt->format('j M Y, g:i A') }}</span>@endif
  </div>

  <div class="notes">
    Prices include GST. Tax is not payable on reverse charge. Please keep this invoice for your records.
    @if (! $seller['gstin'])<br>GSTIN not provided by the seller.@endif
    @if ($seller['email'])<br>Questions about this invoice? Write to {{ $seller['email'] }} quoting {{ $invoice->invoice_number }}.@endif
  </div>
</body>
</html>
