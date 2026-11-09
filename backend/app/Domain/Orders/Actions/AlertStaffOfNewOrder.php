<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Identity\PermissionCatalog;
use App\Models\Order;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Puts a "new order" alert in the admin bell of every active staff member who can see
 * orders. Runs after the payment is committed; a failure here never touches the payment.
 */
class AlertStaffOfNewOrder
{
    public function __invoke(Order $order): void
    {
        try {
            $recipients = User::query()->permission('orders.view')->where('is_active', true)->get()
                ->merge(User::query()->role(PermissionCatalog::SUPER_ADMIN)->where('is_active', true)->get())
                ->unique('id');

            Notification::sendNow($recipients, new NewOrderNotification($order));
        } catch (Throwable $e) {
            Log::channel('orders')->error('New-order staff alert failed.', ['order' => $order->order_number, 'error' => $e->getMessage()]);
            report($e);
        }
    }
}
