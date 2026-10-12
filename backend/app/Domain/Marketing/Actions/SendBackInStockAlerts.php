<?php

namespace App\Domain\Marketing\Actions;

use App\Models\StockAlert;
use App\Notifications\BackInStockNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Scheduled: emails everyone waiting for a variant that is available again, once.
 * Each alert is claimed (notified_at set) before its email is queued, so a run that
 * overlaps another never emails twice.
 */
class SendBackInStockAlerts
{
    public function __invoke(int $batch = 200): int
    {
        $sent = 0;
        $alerts = StockAlert::query()->whereNull('notified_at')
            ->with(['variant.inventory', 'variant.product'])
            ->orderBy('id')->limit($batch)->get();

        foreach ($alerts as $alert) {
            $variant = $alert->variant;
            $product = $variant?->product;
            if (! $variant || ! $product || ! $variant->is_active || $product->status->value !== 'active' || ($variant->available() ?? 1) <= 0) {
                continue;
            }

            $claimed = DB::table('stock_alerts')->where('id', $alert->id)->whereNull('notified_at')->update(['notified_at' => now(), 'updated_at' => now()]);
            if ($claimed === 1) {
                Notification::route('mail', $alert->email)->notify(new BackInStockNotification($product, $variant));
                $sent++;
            }
        }

        if ($sent > 0) {
            Log::channel('orders')->info('Back-in-stock alerts sent.', ['count' => $sent]);
        }

        return $sent;
    }
}
