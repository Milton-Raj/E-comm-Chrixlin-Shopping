<?php

namespace App\Jobs;

use App\Domain\Orders\OrderAlertSettings;
use App\Models\Order;
use App\Support\Money\Money;
use App\Support\WhatsApp\WhatsAppClient;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp message to the owner for a newly paid order. First tried straight after the
 * payment response (no waiting for the queue); if WhatsApp is briefly unavailable it goes
 * on the queue and is retried with back-off. A failure never affects the order.
 */
class SendWhatsAppOrderAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 180, 600, 1800];

    public function __construct(public readonly Order $order) {}

    /** Sends as soon as the current request has answered. */
    public static function soon(Order $order): void
    {
        self::dispatchAfterResponse($order);
    }

    public function handle(WhatsAppClient $client, OrderAlertSettings $settings): void
    {
        $to = $settings->whatsappNumber();
        if (! $settings->whatsappEnabled() || $to === null || ! $client->configured()) {
            return;
        }

        try {
            $client->sendTemplate($to, (string) config('services.whatsapp.order_template'), (string) config('services.whatsapp.template_language'), self::parameters($this->order));
            Log::channel('orders')->info('WhatsApp order alert sent.', ['order' => $this->order->order_number, 'to' => WhatsAppClient::mask($to)]);
        } catch (WhatsAppException $e) {
            if (! $e->retryable) {
                Log::channel('orders')->error('WhatsApp order alert not sent: '.$e->getMessage(), ['order' => $this->order->order_number]);

                return;
            }
            if ($this->job === null || $this->job instanceof SyncJob) {
                // Ran right after the response (Laravel runs that through the sync queue): hand over to the real queue for retries.
                self::dispatch($this->order)->onQueue('critical')->delay(now()->addMinute());

                return;
            }
            throw $e; // queued run: let the queue retry with back-off
        }
    }

    /**
     * The template's five variables: order number, amount, items, where it goes, admin link.
     * No customer name, phone or address line is sent to WhatsApp.
     *
     * @return list<string>
     */
    public static function parameters(Order $order): array
    {
        $order->loadMissing(['items', 'addresses']);
        $rupees = Money::of($order->grand_total, $order->currency)->format();
        $amount = $order->presentment_currency ? Money::of($order->chargeAmount(), $order->chargeCurrency())->format()." ({$rupees})" : $rupees;
        $address = $order->shippingAddress();
        $place = $address
            ? $address->city.', '.($address->country_code === config('commerce.store.country')
                ? (string) config("regions.states.{$address->country_code}.{$address->state_code}", $address->state_code)
                : (string) config("regions.countries.{$address->country_code}", $address->country_code))
            : 'Digital delivery';
        $items = (int) $order->items->sum('quantity');

        return [
            $order->order_number,
            $amount,
            $items.' '.($items === 1 ? 'item' : 'items'),
            $place,
            rtrim((string) config('commerce.frontend_url'), '/').'/admin/orders/'.$order->order_number,
        ];
    }
}
