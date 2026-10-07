<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DigitalDownload;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Store analytics for the admin dashboard (PRD §41, §73): KPIs with the previous
 * period for comparison, time series, product/category/status breakdowns.
 * Revenue = paid orders minus refunds, by paid date.
 */
class DashboardController extends Controller
{
    private const PAID = ['captured', 'partially_refunded', 'refunded'];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['range' => ['nullable', Rule::in(['7', '30', '90', '365'])]]);
        $range = (int) ($validated['range'] ?? 30);
        $currency = (string) config('commerce.store.currency');
        $money = fn (int $v) => Money::of($v, $currency)->toArray();

        $to = now()->endOfDay();
        $from = now()->subDays($range - 1)->startOfDay();
        $prevTo = $from->copy()->subSecond();
        $prevFrom = $from->copy()->subDays($range);

        $current = $this->kpis($from, $to);
        $previous = $this->kpis($prevFrom, $prevTo);

        return ApiResponse::success([
            'range' => ['days' => $range, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'bucket' => $range > 90 ? 'month' : 'day'],
            'kpis' => [
                'revenue' => ['current' => $money($current['revenue']), 'previous' => $money($previous['revenue'])],
                'orders' => ['current' => $current['orders'], 'previous' => $previous['orders']],
                'average_order_value' => ['current' => $money($current['aov']), 'previous' => $money($previous['aov'])],
                'new_customers' => ['current' => $current['new_customers'], 'previous' => $previous['new_customers']],
                'conversion' => ['current' => $current['conversion'], 'previous' => $previous['conversion']],
                'units_sold' => ['current' => $current['units'], 'previous' => $previous['units']],
                'refunds' => ['current' => $money($current['refunds']), 'previous' => $money($previous['refunds'])],
                'downloads' => ['current' => $current['downloads'], 'previous' => $previous['downloads']],
            ],
            'today' => ['revenue' => $money($this->kpis(Carbon::today(), $to)['revenue']), 'orders' => $this->kpis(Carbon::today(), $to)['orders']],
            'totals' => [
                'customers' => User::query()->role('customer')->count(),
                'staff' => User::query()->whereHas('roles', fn ($q) => $q->where('name', '!=', 'customer'))->count(),
                'active_products' => Product::query()->where('status', 'active')->count(),
                'digital_products' => Product::query()->where('status', 'active')->where('product_type', 'digital')->count(),
                'orders_to_fulfil' => Order::query()->where('status', 'processing')->count(),
                'low_stock_variants' => Inventory::query()->whereRaw('on_hand - reserved <= low_stock_threshold')->count(),
                'needs_attention' => Order::query()->where('requires_attention', true)->count(),
            ],
            'series' => $this->series($from, $to, $range > 90 ? 'month' : 'day'),
            'top_products' => $this->topProducts($from, $to, $prevFrom, $prevTo, $money),
            'by_category' => $this->byCategory($from, $to, $money),
            'by_type' => $this->byType($from, $to, $money),
            'orders_by_status' => DB::table('orders')->whereNotNull('placed_at')->whereBetween('placed_at', [$from, $to])
                ->selectRaw('status, COUNT(*) as total')->groupBy('status')->orderByDesc('total')->get()
                ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->total]),
            'low_stock' => DB::table('inventory')->join('product_variants', 'product_variants.id', '=', 'inventory.variant_id')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereNull('product_variants.deleted_at')->where('products.status', 'active')
                ->whereRaw('inventory.on_hand - inventory.reserved <= inventory.low_stock_threshold')
                ->orderByRaw('inventory.on_hand - inventory.reserved')->limit(8)
                ->get(['products.name', 'products.uuid', 'product_variants.name as variant', 'product_variants.sku', DB::raw('inventory.on_hand - inventory.reserved as available')])
                ->map(fn ($r) => ['name' => $r->name, 'product_uuid' => $r->uuid, 'variant' => $r->variant, 'sku' => $r->sku, 'available' => (int) $r->available]),
            'recent_orders' => Order::query()->whereNotNull('placed_at')->latest('placed_at')->limit(8)->get()
                ->map(fn (Order $o) => ['order_number' => $o->order_number, 'email' => $o->email, 'status' => $o->status->value, 'payment_status' => $o->payment_status->value, 'total' => $money($o->grand_total), 'placed_at' => $o->placed_at?->toIso8601String()]),
        ]);
    }

    /** @return array{revenue: int, orders: int, aov: int, new_customers: int, conversion: float, units: int, refunds: int, downloads: int} */
    private function kpis(Carbon $from, Carbon $to): array
    {
        $paid = DB::table('orders')->whereIn('payment_status', self::PAID)->whereBetween('paid_at', [$from, $to]);
        $row = (clone $paid)->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total - refunded_total), 0) as revenue, COALESCE(SUM(refunded_total), 0) as refunds')->first();
        $orders = (int) $row->orders;
        $placed = Order::query()->whereBetween('placed_at', [$from, $to])->count();
        $units = (int) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', self::PAID)->whereBetween('orders.paid_at', [$from, $to])->sum('order_items.quantity');

        return [
            'revenue' => (int) $row->revenue,
            'orders' => $orders,
            'aov' => $orders ? intdiv((int) $row->revenue, $orders) : 0,
            'new_customers' => User::query()->role('customer')->whereBetween('created_at', [$from, $to])->count(),
            'conversion' => $placed ? round($orders / $placed * 100, 1) : 0.0,
            'units' => $units,
            'refunds' => (int) $row->refunds,
            'downloads' => DigitalDownload::query()->where('status', 'completed')->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    /** @return list<array{bucket: string, revenue: int, orders: int, new_customers: int}> */
    private function series(Carbon $from, Carbon $to, string $bucket): array
    {
        $format = $bucket === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $paid = DB::table('orders')->whereIn('payment_status', self::PAID)->whereBetween('paid_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(paid_at, '{$format}') as b, SUM(grand_total - refunded_total) as revenue, COUNT(*) as orders")
            ->groupBy('b')->get()->keyBy('b');
        $users = User::query()->role('customer')->whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(users.created_at, '{$format}') as b, COUNT(*) as total")->groupBy('b')->pluck('total', 'b');

        $points = [];
        $cursor = $from->copy();
        while ($cursor <= $to) {
            $key = $bucket === 'month' ? $cursor->format('Y-m') : $cursor->toDateString();
            $points[] = [
                'bucket' => $key,
                'revenue' => (int) ($paid[$key]->revenue ?? 0),
                'orders' => (int) ($paid[$key]->orders ?? 0),
                'new_customers' => (int) ($users[$key] ?? 0),
            ];
            $bucket === 'month' ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }

        return $points;
    }

    /**
     * Best sellers by units, with the change vs the previous period ("trending").
     *
     * @return list<array<string, mixed>>
     */
    private function topProducts(Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo, \Closure $money): array
    {
        $units = fn (Carbon $a, Carbon $b) => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', self::PAID)->whereBetween('orders.paid_at', [$a, $b])
            ->selectRaw('order_items.name, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.name');

        $previous = $units($prevFrom, $prevTo)->get()->keyBy('name');

        return $units($from, $to)->orderByDesc('units')->orderByDesc('revenue')->limit(8)->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'units' => (int) $r->units,
                'revenue' => $money((int) $r->revenue),
                'previous_units' => (int) ($previous[$r->name]->units ?? 0),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function byCategory(Carbon $from, Carbon $to, \Closure $money): array
    {
        return DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('orders.payment_status', self::PAID)->whereBetween('orders.paid_at', [$from, $to])
            ->selectRaw("COALESCE(categories.name, 'Uncategorised') as category, SUM(order_items.line_total) as revenue, SUM(order_items.quantity) as units")
            ->groupBy('category')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['category' => $r->category, 'revenue' => $money((int) $r->revenue), 'units' => (int) $r->units])->all();
    }

    /** @return list<array<string, mixed>> */
    private function byType(Carbon $from, Carbon $to, \Closure $money): array
    {
        $rows = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', self::PAID)->whereBetween('orders.paid_at', [$from, $to])
            ->selectRaw('order_items.product_type as type, SUM(order_items.line_total) as revenue, SUM(order_items.quantity) as units')
            ->groupBy('type')->get()->keyBy('type');

        return array_map(fn (string $type) => [
            'type' => $type,
            'revenue' => $money((int) ($rows[$type]->revenue ?? 0)),
            'units' => (int) ($rows[$type]->units ?? 0),
        ], ['physical', 'digital']);
    }
}
