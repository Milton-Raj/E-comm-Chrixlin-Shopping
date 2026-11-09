<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money\Money;
use Illuminate\Notifications\Notification;

/**
 * In-admin alert for a newly paid order (the bell, with a chime). Stored, not mailed;
 * written straight away so it shows on the next poll. Holds no address or payment detail.
 */
class NewOrderNotification extends Notification
{
    public function __construct(public readonly Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'order_paid';
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $order = $this->order;
        $order->loadMissing(['items', 'addresses']);

        return [
            'order_number' => $order->order_number,
            'customer' => $order->shippingAddress()->name ?? $order->email,
            'item_count' => (int) $order->items->sum('quantity'),
            'total' => Money::of($order->grand_total, $order->currency)->toArray(),
            'charged' => $order->presentment_currency ? Money::of($order->chargeAmount(), $order->chargeCurrency())->toArray() : null,
            'country_code' => $order->shippingAddress()?->country_code,
        ];
    }
}
