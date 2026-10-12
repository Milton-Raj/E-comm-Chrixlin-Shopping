<?php

namespace App\Notifications;

use App\Domain\Cart\PricedCart;
use App\Domain\Cart\PricedLine;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/** The single "you left something in your bag" email, with a link that reopens the bag. */
class AbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @var list<array{name: string, quantity: int}> */
    public readonly array $lines;

    public readonly string $total;

    public readonly string $cartUuid;

    public function __construct(PricedCart $priced, public readonly bool $signedInCart)
    {
        // Snapshot plain values: the queued job must not re-price or carry models around.
        $this->lines = array_map(fn (PricedLine $l) => [
            'name' => $l->item->variant->product->name.($l->item->variant->name ? " ({$l->item->variant->name})" : ''),
            'quantity' => $l->quantity,
        ], $priced->sellableLines());
        $this->total = Money::of($priced->subtotal, $priced->currency)->format();
        $this->cartUuid = $priced->cart->uuid;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $store = (string) config('commerce.store.name');
        $link = $this->signedInCart
            ? rtrim((string) config('commerce.frontend_url'), '/').'/cart'
            : URL::temporarySignedRoute('v1.cart.restore', now()->addDays(7), ['cart' => $this->cartUuid]);

        $mail = (new MailMessage)
            ->subject("You left something lovely in your bag — {$store}")
            ->greeting('Still thinking it over?')
            ->line('Your bag is saved and waiting:');
        foreach ($this->lines as $line) {
            $mail->line("• {$line['name']} × {$line['quantity']}");
        }

        return $mail->line("Bag total: {$this->total}")
            ->line('Each piece is handmade in small batches, so popular items can sell out.')
            ->action('Return to your bag', $link)
            ->line('This is a one-time reminder — we won\'t email you about this bag again.');
    }
}
