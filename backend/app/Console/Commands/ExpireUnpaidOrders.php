<?php

namespace App\Console\Commands;

use App\Domain\Orders\Actions\CancelOrder;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Releases stock held by orders whose payment never completed (PRD §34).
 */
#[Signature('orders:expire-unpaid {--minutes=60}')]
#[Description('Cancel unpaid orders older than the reservation window and release their stock')]
class ExpireUnpaidOrders extends Command
{
    public function handle(CancelOrder $cancel): int
    {
        $cutoff = now()->subMinutes((int) $this->option('minutes'));
        $count = 0;

        Order::query()
            ->whereIn('status', ['pending', 'payment_processing', 'failed'])
            ->whereIn('payment_status', [PaymentStatus::Initiated->value, PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->where('created_at', '<', $cutoff)
            ->each(function (Order $order) use ($cancel, &$count) {
                $cancel->handle($order, 'system', null, 'Payment not completed in time');
                $count++;
            });

        $this->info("Expired {$count} unpaid orders.");

        return self::SUCCESS;
    }
}
