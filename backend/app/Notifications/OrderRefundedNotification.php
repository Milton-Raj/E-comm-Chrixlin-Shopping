<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the customer their order was cancelled (full refund) or part of it refunded. */
class OrderRefundedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order, public readonly int $amount, public readonly bool $fully) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $money = Money::of($this->amount, $this->order->currency)->format();
        $mail = (new MailMessage)
            ->subject($this->fully ? "Order {$number} cancelled: your refund is on its way" : "A refund for order {$number} is on its way")
            ->greeting($this->fully ? 'Your order has been cancelled' : 'Your refund is on its way');

        $mail->line($this->fully
            ? "Order {$number} has been cancelled, and {$money} is on its way back to your account."
            : "We've refunded {$money} for order {$number}.");

        return $mail
            ->line('The money goes back to the card, UPI or bank account you paid with. Most banks show it within 5–7 working days.')
            ->line('If you have any questions, just reply to this email or write to '.config('mail.from.address').' with your order number.')
            ->action('View your order', rtrim((string) config('commerce.frontend_url'), '/').'/orders/'.$number);
    }
}
