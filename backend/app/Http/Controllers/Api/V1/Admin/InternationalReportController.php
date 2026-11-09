<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Reports\InternationalReport;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Admin → International: month-end FX report and exchange-rate history (`reports.finance`). */
class InternationalReportController extends Controller
{
    public function report(Request $request, InternationalReport $report): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $r = $report->build($from, $to);
        $base = (string) config('commerce.store.currency');
        $money = fn (int $amount, string $currency) => Money::of($amount, $currency)->toArray();

        return ApiResponse::success([
            'range' => $this->rangeOut($from, $to),
            'store_currency' => $base,
            'by_currency' => array_map(fn ($c) => [
                'currency' => $c['currency'], 'orders' => $c['orders'], 'refunds' => $c['refunds'], 'average_rate' => $c['average_rate'],
                'charged' => $money($c['charged'], $c['currency']), 'booked' => $money($c['booked'], $base),
                'refunded_charged' => $money($c['refunded_charged'], $c['currency']), 'refunded_booked' => $money($c['refunded_booked'], $base),
                'net_charged' => $money($c['net_charged'], $c['currency']), 'net_booked' => $money($c['net_booked'], $base),
            ], $r['by_currency']),
            'by_month' => array_map(fn ($m) => [
                'month' => $m['month'], 'currency' => $m['currency'], 'orders' => $m['orders'], 'refunds' => $m['refunds'],
                'average_rate' => $m['average_rate'], 'rate_low' => $m['rate_low'], 'rate_high' => $m['rate_high'],
                'charged' => $money($m['charged'], $m['currency']), 'booked' => $money($m['booked'], $base),
                'net_charged' => $money($m['net_charged'], $m['currency']), 'net_booked' => $money($m['net_booked'], $base),
            ], $r['by_month']),
            'orders' => $r['orders']->map(fn ($o) => [
                ...$o,
                'paid_at' => $o['paid_at']?->toIso8601String(),
                'rate_fetched_at' => $o['rate_fetched_at']?->toIso8601String(),
                'charged' => $money($o['charged'], $o['currency']),
                'booked' => $money($o['booked'], $base),
                'refunded_booked' => $money($o['refunded_booked'], $base),
            ]),
            'rates' => $r['rates'],
        ]);
    }

    public function history(Request $request, InternationalReport $report): JsonResponse
    {
        $data = $request->validate(['currency' => ['required', 'string', Rule::in((array) config('localization.offerable_currencies'))]]);
        [$from, $to] = $this->range($request);

        return ApiResponse::success([
            'range' => $this->rangeOut($from, $to),
            'currency' => $data['currency'],
            'items' => $report->history($data['currency'], $from, $to),
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $range = InternationalReport::range($data['from'] ?? null, $data['to'] ?? null);
        if ($range[0]->diffInDays($range[1]) > InternationalReport::MAX_DAYS) {
            throw new ApiException('Choose a period of up to two years.', 422, null, ['to' => ['Choose a period of up to two years.']]);
        }

        return $range;
    }

    /** @return array{from: string, to: string} */
    private function rangeOut(Carbon $from, Carbon $to): array
    {
        $zone = (string) config('commerce.store.timezone', 'UTC');

        return ['from' => $from->copy()->setTimezone($zone)->toDateString(), 'to' => $to->copy()->setTimezone($zone)->toDateString()];
    }
}
