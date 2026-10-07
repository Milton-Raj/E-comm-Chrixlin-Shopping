<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ORDER_CONFIRMATION + PAYMENT_SUCCESS + DIGITAL_PRODUCT_READY (PRD §40) in one email.
 */
class OrderPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $mail = (new MailMessage)
            ->subject("Order {$order->order_number} confirmed")
            ->greeting('Thank you for your order')
            ->line("We've received your payment for order {$order->order_number}.");

        foreach ($order->items as $item) {
            $mail->line("{$item->quantity} × {$item->name}".($item->variant_name ? " ({$item->variant_name})" : '').' — '.Money::of($item->line_total, $order->currency)->format());
        }

        $mail->line('Total paid: '.Money::of($order->grand_total, $order->currency)->format());

        if ($order->entitlements->isNotEmpty()) {
            $mail->line('Your digital items are ready to download from your account or the order page.');
        }

        return $mail->action('View your order', rtrim((string) config('commerce.frontend_url'), '/').'/orders/'.$order->order_number);
    }
}
