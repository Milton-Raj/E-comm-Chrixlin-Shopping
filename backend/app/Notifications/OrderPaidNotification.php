<?php

namespace App\Notifications;

use App\Domain\Invoices\InvoiceDocument;
use App\Models\Order;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ORDER_CONFIRMATION + PAYMENT_SUCCESS + DIGITAL_PRODUCT_READY (PRD §40) in one email,
 * with the GST tax invoice attached as a PDF.
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

        // Abroad, amounts are shown in the currency the customer paid, at the order's locked rate.
        $presentment = $order->presentment();
        $line = fn (int $amount) => ($presentment ? $presentment->money($amount) : Money::of($amount, $order->currency))->format();
        foreach ($order->items as $item) {
            $mail->line("{$item->quantity} × {$item->name}".($item->variant_name ? " ({$item->variant_name})" : '').' — '.$line($item->line_total));
        }

        $mail->line('Total paid: '.Money::of($order->chargeAmount(), $order->chargeCurrency())->format());
        if ($presentment && $order->tax_total > 0) {
            $codes = implode(' + ', array_keys($order->tax_breakdown ?? []));
            $mail->line('This includes '.$line($order->tax_total).' Indian GST'.($codes !== '' ? " ({$codes})" : '').'. We ship from India, so Indian GST applies under Indian law; it is already included in the price.');
        }

        if ($order->entitlements->isNotEmpty()) {
            $mail->line('Your digital items are ready to download from your account or the order page.');
        }

        $mail->action('View your order', rtrim((string) config('commerce.frontend_url'), '/').'/orders/'.$order->order_number);

        // The GST tax invoice travels with the confirmation as a PDF attachment.
        $invoice = $order->invoice()->first();
        if ($invoice) {
            $document = app(InvoiceDocument::class);
            $mail->line("Your tax invoice {$invoice->invoice_number} is attached as a PDF for your records.")
                ->attachData($document->pdf($invoice), $document->filename($invoice), ['mime' => 'application/pdf']);
            $invoice->forceFill(['emailed_at' => now()])->save();
        }

        return $mail;
    }
}
