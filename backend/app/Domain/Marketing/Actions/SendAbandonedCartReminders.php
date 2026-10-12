<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Cart\CartPricer;
use App\Domain\Marketing\MarketingSettings;
use App\Models\Cart;
use App\Notifications\AbandonedCartNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Scheduled: one reminder per abandoned bag (PRD §72), only when the owner switched it
 * on and the shopper gave an email. Bags older than 7 days are left alone.
 */
class SendAbandonedCartReminders
{
    public function __construct(private readonly MarketingSettings $settings, private readonly CartPricer $pricer) {}

    public function __invoke(int $batch = 100): int
    {
        if (! $this->settings->abandonedCartEnabled()) {
            return 0;
        }

        $carts = Cart::query()->where('status', 'active')->whereNull('reminder_sent_at')
            ->where('last_activity_at', '<=', now()->subHours($this->settings->abandonedCartDelayHours()))
            ->where('last_activity_at', '>=', now()->subDays(7))
            ->whereHas('items')
            ->with('user')
            ->orderBy('id')->limit($batch)->get();

        $sent = 0;
        foreach ($carts as $cart) {
            $email = $cart->email ?: $cart->user?->email;
            if (! $email) {
                continue;
            }
            $priced = $this->pricer->price($cart);
            if ($priced->sellableLines() === []) {
                continue;
            }

            // Claim first so overlapping runs never send twice.
            if (DB::table('carts')->where('id', $cart->id)->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]) !== 1) {
                continue;
            }
            Notification::route('mail', $email)->notify(new AbandonedCartNotification($priced, $cart->user_id !== null));
            $sent++;
        }

        if ($sent > 0) {
            Log::channel('orders')->info('Abandoned bag reminders sent.', ['count' => $sent]);
        }

        return $sent;
    }
}
