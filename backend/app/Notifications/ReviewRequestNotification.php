<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The single "how was it?" email after delivery, linking straight to each product's review form. */
class ReviewRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<array{name: string, path: string}>  $products */
    public function __construct(public readonly string $orderNumber, public readonly array $products) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $store = (string) config('commerce.store.name');
        $base = rtrim((string) config('commerce.frontend_url'), '/');
        $link = fn (array $p) => $base.$p['path'].'#write-review';

        $mail = (new MailMessage)
            ->subject("How are you enjoying your order? — {$store}")
            ->greeting('We hope you love it')
            ->line("Your order {$this->orderNumber} arrived a few days ago. A short review helps other shoppers choose, and tells our makers what you think.");

        if (count($this->products) === 1) {
            return $mail->action('Rate '.$this->products[0]['name'], $link($this->products[0]))
                ->line('It only takes a minute — tap the stars, add a line or two, and a photo if you like.');
        }

        $mail->line('Tap a piece to rate it:');
        foreach ($this->products as $p) {
            $mail->line("• [{$p['name']}]({$link($p)})");
        }

        return $mail->action('Rate '.$this->products[0]['name'], $link($this->products[0]))
            ->line('It only takes a minute — tap the stars, add a line or two, and a photo if you like.');
    }
}
