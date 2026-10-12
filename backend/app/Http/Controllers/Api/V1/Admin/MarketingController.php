<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Cart\CartPricer;
use App\Domain\Marketing\MarketingSettings;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Marketing views for the owner: demand waiting on restocks and abandoned bags. */
class MarketingController extends Controller
{
    /** Sold-out variants shoppers are waiting for, most-wanted first. */
    public function stockAlerts(): JsonResponse
    {
        $rows = DB::table('stock_alerts')->whereNull('notified_at')
            ->selectRaw('variant_id, COUNT(*) AS waiting, MAX(created_at) AS last_requested_at')
            ->groupBy('variant_id')->orderByDesc('waiting')->limit(100)->get();
        $variants = ProductVariant::withTrashed()->with('inventory')->whereIn('id', $rows->pluck('variant_id'))->get()->keyBy('id');
        $products = Product::withTrashed()->whereIn('id', $variants->pluck('product_id'))->get()->keyBy('id');

        return ApiResponse::success([
            'waiting' => $rows->map(function ($row) use ($variants, $products) {
                $variant = $variants[$row->variant_id] ?? null;
                $product = $variant ? ($products[$variant->product_id] ?? null) : null;

                return [
                    'product' => $product ? ['uuid' => $product->uuid, 'name' => $product->name] : null,
                    'variant' => $variant ? ['sku' => $variant->sku, 'name' => $variant->name] : null,
                    'waiting' => (int) $row->waiting,
                    'available' => $variant?->available(),
                    'last_requested_at' => $row->last_requested_at ? date(DATE_ATOM, strtotime((string) $row->last_requested_at)) : null,
                ];
            })->values(),
            'notified_last_30_days' => StockAlert::query()->where('notified_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    /** Bags left with items for longer than the reminder delay (last 30 days), and how many reminders came back as orders. */
    public function abandonedCarts(CartPricer $pricer, MarketingSettings $settings): JsonResponse
    {
        $carts = Cart::query()->where('status', 'active')->whereHas('items')
            ->where('last_activity_at', '<=', now()->subHours($settings->abandonedCartDelayHours()))
            ->where('last_activity_at', '>=', now()->subDays(30))
            ->with('user')->latest('last_activity_at')->limit(50)->get();
        $since = now()->subDays(30);

        return ApiResponse::success([
            'enabled' => $settings->abandonedCartEnabled(),
            'delay_hours' => $settings->abandonedCartDelayHours(),
            'carts' => $carts->map(function (Cart $cart) use ($pricer) {
                $priced = $pricer->price($cart);

                return [
                    'uuid' => $cart->uuid,
                    'email' => $cart->email ?: $cart->user?->email,
                    'customer' => $cart->user?->name,
                    'items' => $priced->itemCount(),
                    'value' => Money::of($priced->subtotal, $priced->currency)->toArray(),
                    'last_activity_at' => $cart->last_activity_at?->toIso8601String(),
                    'reminder_sent_at' => $cart->reminder_sent_at?->toIso8601String(),
                ];
            })->values(),
            'reminders_sent_30d' => Cart::query()->where('reminder_sent_at', '>=', $since)->count(),
            'recovered_30d' => Cart::query()->where('reminder_sent_at', '>=', $since)->where('status', 'converted')->count(),
        ]);
    }
}
