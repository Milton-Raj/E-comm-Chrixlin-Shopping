<?php

namespace App\Domain\Reports;

use App\Models\ExchangeRateHistory;
use App\Models\Order;
use App\Models\Refund;
use App\Support\Money\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Month-end FX audit (ARCHITECTURE D21). Every order abroad was charged at the rate locked
 * when it was placed, so it is accounted one by one: what the customer paid, the market
 * rate and markup behind it, and the rupee value booked. Sales count by payment date and
 * refunds by refund date, both in the store's time zone.
 */
class InternationalReport
{
    /** Longest period one report covers (two financial years). */
    public const MAX_DAYS = 731;

    /**
     * A period in the store's time zone (a calendar month by default), as UTC instants.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(?string $from, ?string $to): array
    {
        $zone = (string) config('commerce.store.timezone', 'UTC');
        $start = $from ? Carbon::parse($from, $zone)->startOfDay() : now($zone)->startOfMonth();
        $end = $to ? Carbon::parse($to, $zone)->endOfDay() : $start->copy()->endOfMonth();

        return [$start->utc(), $end->utc()];
    }

    /**
     * @return array{
     *   by_currency: list<array{currency: string, orders: int, charged: int, booked: int, refunds: int, refunded_charged: int, refunded_booked: int, net_charged: int, net_booked: int, average_rate: string|null}>,
     *   by_month: list<array{month: string, currency: string, orders: int, charged: int, booked: int, refunds: int, refunded_charged: int, refunded_booked: int, net_charged: int, net_booked: int, average_rate: string|null, rate_low: string|null, rate_high: string|null}>,
     *   orders: Collection<int, array{order_number: string, paid_at: Carbon|null, country: string|null, currency: string, charged: int, market_rate: string|null, markup_bps: int|null, applied_rate: string, rate_fetched_at: Carbon|null, booked: int, refunded_booked: int, payment_reference: string|null}>,
     *   refunds: Collection<int, array{order_number: mixed, refunded_at: Carbon, currency: string, charged: int, booked: int, reason: string, foreign: bool}>,
     *   rates: list<array{currency: string, fetches: int, low: string, high: string, first: string, last: string}>
     * }
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $base = (string) config('commerce.store.currency');
        $paid = Order::query()->whereNotNull('paid_at')->whereBetween('paid_at', [$from, $to]);
        $refunds = Refund::query()->whereBetween('refunds.created_at', [$from, $to])->where('refunds.status', 'processed');

        $orders = (clone $paid)->whereNotNull('presentment_currency')->with(['addresses', 'latestPayment'])->orderBy('paid_at')->get()
            ->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'paid_at' => $o->paid_at,
                'country' => $o->shippingAddress()?->country_code,
                'currency' => (string) $o->presentment_currency,
                'charged' => (int) $o->presentment_total,
                'market_rate' => $o->market_rate !== null ? $this->trim($o->market_rate) : null,
                'markup_bps' => $o->exchange_markup_bps,
                'applied_rate' => $this->trim((string) $o->exchange_rate),
                'rate_fetched_at' => $o->exchange_rate_fetched_at,
                'booked' => $o->grand_total,
                'refunded_booked' => $o->refunded_total,
                'payment_reference' => $o->latestPayment?->provider_payment_id,
            ])->values();

        $refundRows = (clone $refunds)->join('orders', 'orders.id', '=', 'refunds.order_id')->orderBy('refunds.created_at')
            ->get(['refunds.*', 'orders.order_number', 'orders.presentment_currency', 'orders.exchange_rate'])
            ->map(fn (Refund $r) => [
                'order_number' => $r->getAttribute('order_number'),
                'refunded_at' => $r->created_at,
                'currency' => $r->gateway_currency ?? $r->currency,
                'charged' => $r->gateway_amount ?? $r->amount,
                'booked' => $r->amount,
                'reason' => $r->reason,
                'foreign' => $r->getAttribute('presentment_currency') !== null,
            ])->values();

        // Every paid order, home and abroad, so each currency's total is complete; also split by month (store time).
        $zone = (string) config('commerce.store.timezone', 'UTC');
        $monthOf = fn (Carbon $at) => $at->copy()->setTimezone($zone)->format('Y-m');
        $byCurrency = [];
        $byMonth = [];
        foreach ((clone $paid)->get(['grand_total', 'presentment_currency', 'presentment_total', 'paid_at']) as $o) {
            $code = $o->presentment_currency ?? $base;
            $charged = $o->presentment_total ?? $o->grand_total;
            $this->add($byCurrency, $code, null, orders: 1, charged: $charged, booked: $o->grand_total);
            $this->add($byMonth, $code, $monthOf($o->paid_at), orders: 1, charged: $charged, booked: $o->grand_total);
        }
        foreach ($refundRows as $r) {
            $this->add($byCurrency, $r['currency'], null, refunds: 1, refundedCharged: $r['charged'], refundedBooked: $r['booked']);
            $this->add($byMonth, $r['currency'], $monthOf($r['refunded_at']), refunds: 1, refundedCharged: $r['charged'], refundedBooked: $r['booked']);
        }
        $finish = fn (array $r) => [
            ...$r,
            'net_charged' => $r['charged'] - $r['refunded_charged'],
            'net_booked' => $r['booked'] - $r['refunded_booked'],
            // Weighted rate actually realised: total charged ÷ rupees booked.
            'average_rate' => $r['currency'] !== $base && $r['booked'] > 0 ? $this->averageRate($r['charged'], $r['currency'], $r['booked'], $base) : null,
        ];
        $byCurrency = collect($byCurrency)
            ->sortBy(fn ($r) => [$r['currency'] === $base ? 0 : 1, $r['currency']])
            ->map(fn ($r) => collect($finish($r))->except('month')->all())->values()->all();

        $history = ExchangeRateHistory::query()->where('base_currency', $base)->whereBetween('fetched_at', [$from, $to])->orderBy('fetched_at')->get();
        $range = function (Collection $rows): array {
            // Ordered numerically (as text, "10.2" would sort before "1.6"); the stored strings are shown.
            $byRate = $rows->sort(fn ($a, $b) => BigDecimal::of($a->rate)->compareTo($b->rate))->values();

            return ['low' => $this->trim($byRate->first()->rate), 'high' => $this->trim($byRate->last()->rate)];
        };
        $rates = $history->groupBy('currency')
            ->map(fn (Collection $rows, string $code) => [
                'currency' => $code,
                'fetches' => $rows->count(),
                ...$range($rows),
                'first' => $this->trim($rows->first()->rate),
                'last' => $this->trim($rows->last()->rate),
            ])->sortKeys()->values()->all();

        // Each month's market-rate range per currency, beside that month's sales.
        $monthRates = $history->groupBy(fn (ExchangeRateHistory $h) => $monthOf($h->fetched_at).'|'.$h->currency)->map($range);
        $byMonth = collect($byMonth)
            ->sortBy(fn ($r) => [$r['month'], $r['currency'] === $base ? 0 : 1, $r['currency']])
            ->map(fn ($r) => [...$finish($r), 'rate_low' => $monthRates[$r['month'].'|'.$r['currency']]['low'] ?? null, 'rate_high' => $monthRates[$r['month'].'|'.$r['currency']]['high'] ?? null])
            ->values()->all();

        return ['by_currency' => array_values($byCurrency), 'by_month' => array_values($byMonth), 'orders' => $orders, 'refunds' => $refundRows, 'rates' => array_values($rates)];
    }

    /**
     * Rate history rows for one currency in the period (newest first).
     *
     * @return Collection<int, array{rate: string, source: string, fetched_at: string}>
     */
    public function history(string $currency, Carbon $from, Carbon $to): Collection
    {
        return ExchangeRateHistory::query()->where('base_currency', (string) config('commerce.store.currency'))
            ->where('currency', strtoupper($currency))->whereBetween('fetched_at', [$from, $to])
            ->latest('fetched_at')->limit(2000)->get() // a year of twice-daily fetches is ~730
            ->map(fn (ExchangeRateHistory $h) => ['rate' => $this->trim($h->rate), 'source' => $h->source, 'fetched_at' => $h->fetched_at->toIso8601String()]);
    }

    /**
     * Every rate fetched in the period, oldest first (the export's audit sheet).
     *
     * @return \Generator<int, array{fetched_at: Carbon, currency: string, rate: string, source: string}>
     */
    public function fetches(Carbon $from, Carbon $to): \Generator
    {
        $rows = ExchangeRateHistory::query()->where('base_currency', (string) config('commerce.store.currency'))
            ->whereBetween('fetched_at', [$from, $to])->orderBy('fetched_at')->orderBy('currency');
        foreach ($rows->lazyById(1000) as $h) {
            yield ['fetched_at' => $h->fetched_at, 'currency' => $h->currency, 'rate' => $this->trim($h->rate), 'source' => $h->source];
        }
    }

    /**
     * Adds a sale or a refund to a currency's (or a month and currency's) running totals.
     *
     * @param  array<string, array{month: string|null, currency: string, orders: int, charged: int, booked: int, refunds: int, refunded_charged: int, refunded_booked: int}>  $totals
     */
    private function add(array &$totals, string $currency, ?string $month, int $orders = 0, int $charged = 0, int $booked = 0, int $refunds = 0, int $refundedCharged = 0, int $refundedBooked = 0): void
    {
        $key = ($month ?? '').'|'.$currency;
        $row = $totals[$key] ?? ['month' => $month, 'currency' => $currency, 'orders' => 0, 'charged' => 0, 'booked' => 0, 'refunds' => 0, 'refunded_charged' => 0, 'refunded_booked' => 0];
        $row['orders'] += $orders;
        $row['charged'] += $charged;
        $row['booked'] += $booked;
        $row['refunds'] += $refunds;
        $row['refunded_charged'] += $refundedCharged;
        $row['refunded_booked'] += $refundedBooked;
        $totals[$key] = $row;
    }

    private function averageRate(int $charged, string $currency, int $booked, string $base): string
    {
        return $this->trim((string) BigDecimal::of($charged)->withPointMovedLeft(Money::exponent($currency))
            ->dividedBy(BigDecimal::of($booked)->withPointMovedLeft(Money::exponent($base)), 8, RoundingMode::HalfUp));
    }

    private function trim(string $decimal): string
    {
        return str_contains($decimal, '.') ? rtrim(rtrim($decimal, '0'), '.') : $decimal;
    }
}
