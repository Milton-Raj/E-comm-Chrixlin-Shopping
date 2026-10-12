<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "It's back": sent once to each shopper who asked to be told. */
class BackInStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Product $product, public readonly ProductVariant $variant) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->product->name.($this->variant->name ? " ({$this->variant->name})" : '');
        $path = ($this->product->product_type->value === 'digital' ? '/digital/' : '/product/').$this->product->slug;

        return (new MailMessage)
            ->subject("It's back: {$this->product->name}")
            ->greeting('Good news — it\'s back in stock')
            ->line("{$name} is available again. We make in small batches, so it may not last long.")
            ->action('Shop it now', rtrim((string) config('commerce.frontend_url'), '/').$path)
            ->line('You asked us to let you know, so this is the only email we will send about it.');
    }
}
