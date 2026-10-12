<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Marketing\MarketingSettings;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Review;
use App\Notifications\ReviewRequestNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Scheduled: one "how was it?" email per delivered order, a few days after delivery (PRD §37),
 * listing the pieces the customer has not reviewed yet. Orders delivered more than 30 days
 * before the delay are left alone, so switching it on never emails old customers.
 */
class SendReviewRequests
{
    public function __construct(private readonly MarketingSettings $settings) {}

    public function __invoke(int $batch = 100): int
    {
        if (! $this->settings->reviewRequestEnabled()) {
            return 0;
        }

        $due = now()->subDays($this->settings->reviewRequestDelayDays());
        $orders = Order::query()->where('status', OrderStatus::Delivered->value)->whereNull('review_requested_at')
            ->whereHas('history', fn ($q) => $q->where('to_status', OrderStatus::Delivered->value)
                ->where('created_at', '<=', $due)->where('created_at', '>=', $due->copy()->subDays(30)))
            ->with(['items.product', 'history' => fn ($q) => $q->where('to_status', OrderStatus::Delivered->value)])
            ->orderBy('id')->limit($batch)->get();

        $sent = 0;
        foreach ($orders as $order) {
            // A later "delivered" entry (e.g. after a refund request was withdrawn) restarts the wait.
            $deliveredAt = $order->history->max(fn (OrderStatusHistory $h) => $h->created_at);
            if ($deliveredAt !== null && $deliveredAt->gt($due)) {
                continue;
            }

            // Claim first so overlapping runs never send twice; an order with nothing to review is claimed too.
            if (DB::table('orders')->where('id', $order->id)->whereNull('review_requested_at')->update(['review_requested_at' => now()]) !== 1) {
                continue;
            }

            $products = $this->reviewable($order);
            if ($products === []) {
                continue;
            }
            Notification::route('mail', $order->email)->notify(new ReviewRequestNotification($order->order_number, $products));
            $sent++;
        }

        if ($sent > 0) {
            Log::channel('orders')->info('Review request emails sent.', ['count' => $sent]);
        }

        return $sent;
    }

    /** @return list<array{name: string, path: string}> live products from the order the customer kept and has not reviewed */
    private function reviewable(Order $order): array
    {
        $reviewed = $order->user_id
            ? Review::withTrashed()->where('user_id', $order->user_id)->pluck('product_id')->all()
            : [];

        return $order->items
            ->filter(fn (OrderItem $i) => $i->product !== null && $i->product->status === ProductStatus::Active
                && $i->refunded_quantity < $i->quantity && ! in_array($i->product_id, $reviewed, true))
            ->unique('product_id')
            ->map(fn (OrderItem $i) => [
                'name' => $i->product->name,
                'path' => ($i->product->product_type->value === 'digital' ? '/digital/' : '/product/').$i->product->slug,
            ])
            ->values()->all();
    }
}
