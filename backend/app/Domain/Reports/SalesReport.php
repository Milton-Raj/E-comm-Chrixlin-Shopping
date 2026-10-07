<?php

namespace App\Domain\Reports;

use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales figures for a period, from paid orders by payment date (PRD §73). Shared by the
 * Reports screen and the Excel report so both always show the same numbers. Amounts are
 * minor units of the store currency.
 */
class SalesReport
{
    /**
     * @return array{
     *   summary: array{orders: int, gross: int, discounts: int, shipping: int, tax: int, refunds: int, net: int, aov: int, units: int},
     *   daily: Collection<int, array{date: string, orders: int, revenue: int}>,
     *   by_product: Collection<int, array{name: string, type: string, units: int, revenue: int}>,
     *   by_category: Collection<int, array{category: string, units: int, revenue: int}>
     * }
     */
    public function build(Carbon $from, Carbon $to, ?int $productLimit = 20): array
    {
        $paid = [PaymentStatus::Captured->value, PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value];
        $orders = DB::table('orders')->whereIn('payment_status', $paid)->whereBetween('paid_at', [$from, $to]);
        $items = fn () => DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', $paid)->whereBetween('orders.paid_at', [$from, $to]);

        $s = (clone $orders)->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total),0) as gross, COALESCE(SUM(discount_total),0) as discounts, COALESCE(SUM(shipping_total),0) as shipping, COALESCE(SUM(tax_total),0) as tax, COALESCE(SUM(refunded_total),0) as refunds')->first();

        $byProduct = $items()->selectRaw('order_items.name, order_items.product_type, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.name', 'order_items.product_type')->orderByDesc('revenue')
            ->when($productLimit, fn ($q, $n) => $q->limit($n))->get();

        $byCategory = $items()->leftJoin('products', 'products.id', '=', 'order_items.product_id')->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("COALESCE(categories.name, 'Uncategorised') as category, SUM(order_items.quantity) as units, SUM(order_items.line_total) as revenue")
            ->groupBy('category')->orderByDesc('revenue')->get();

        $daily = (clone $orders)->selectRaw('DATE(paid_at) as day, COUNT(*) as orders, SUM(grand_total - refunded_total) as revenue')->groupBy('day')->orderBy('day')->get();
        $units = (int) $items()->sum('order_items.quantity');

        return [
            'summary' => [
                'orders' => (int) $s->orders, 'gross' => (int) $s->gross, 'discounts' => (int) $s->discounts, 'shipping' => (int) $s->shipping,
                'tax' => (int) $s->tax, 'refunds' => (int) $s->refunds, 'net' => (int) $s->gross - (int) $s->refunds,
                'aov' => $s->orders ? intdiv((int) $s->gross, (int) $s->orders) : 0, 'units' => $units,
            ],
            'daily' => $daily->map(fn ($d) => ['date' => (string) $d->day, 'orders' => (int) $d->orders, 'revenue' => (int) $d->revenue])->values(),
            'by_product' => $byProduct->map(fn ($r) => ['name' => (string) $r->name, 'type' => (string) $r->product_type, 'units' => (int) $r->units, 'revenue' => (int) $r->revenue])->values(),
            'by_category' => $byCategory->map(fn ($r) => ['category' => (string) $r->category, 'units' => (int) $r->units, 'revenue' => (int) $r->revenue])->values(),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} the requested range, defaulting to the last 30 days */
    public static function range(?string $from, ?string $to): array
    {
        return [
            $from ? now()->parse($from)->startOfDay() : now()->subDays(29)->startOfDay(),
            $to ? now()->parse($to)->endOfDay() : now()->endOfDay(),
        ];
    }
}
