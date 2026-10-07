<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Reports\SalesReport;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Sales, product and category reports for a date range (PRD §73). */
class ReportController extends Controller
{
    public function sales(Request $request, SalesReport $report): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = SalesReport::range($data['from'] ?? null, $data['to'] ?? null);
        $currency = (string) config('commerce.store.currency');
        $money = fn (int $v) => Money::of($v, $currency)->toArray();
        $r = $report->build($from, $to);
        $s = $r['summary'];

        return ApiResponse::success([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'orders' => $s['orders'],
                'gross_sales' => $money($s['gross']),
                'discounts' => $money($s['discounts']),
                'shipping' => $money($s['shipping']),
                'tax' => $money($s['tax']),
                'refunds' => $money($s['refunds']),
                'net_sales' => $money($s['net']),
                'average_order_value' => $money($s['aov']),
            ],
            'daily' => $r['daily'],
            'by_product' => $r['by_product']->map(fn ($p) => [...$p, 'revenue' => $money($p['revenue'])]),
            'by_category' => $r['by_category']->map(fn ($c) => [...$c, 'revenue' => $money($c['revenue'])]),
        ]);
    }
}
