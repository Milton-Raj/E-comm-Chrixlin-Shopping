<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** New corporate/bulk gifting enquiry: a copy for the store, an acknowledgement for the sender. */
class EnquiryReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Enquiry $enquiry, public readonly bool $forStore) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $e = $this->enquiry;
        $store = (string) config('commerce.store.name');

        if (! $this->forStore) {
            return (new MailMessage)
                ->subject("We've received your gifting enquiry — {$store}")
                ->greeting("Thank you, {$e->name}")
                ->line('We have your enquiry and will get back to you soon with options, pricing and timelines.')
                ->line('If anything changes, just reply to this email.');
        }

        $mail = (new MailMessage)
            ->subject('New gifting enquiry'.($e->company ? " from {$e->company}" : '')." — {$e->name}")
            ->replyTo($e->email, $e->name)
            ->greeting('New corporate / bulk gifting enquiry');
        foreach ([
            'Name' => $e->name, 'Company' => $e->company, 'Email' => $e->email, 'Phone' => $e->phone,
            'Quantity' => $e->quantity, 'Budget per gift' => $e->budget, 'Occasion' => $e->occasion, 'Needed by' => $e->needed_by?->toFormattedDateString(),
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $mail->line("**{$label}:** {$value}");
            }
        }

        return $mail->line('**Message:**')->line($e->message)
            ->action('Open in admin', rtrim((string) config('commerce.frontend_url'), '/').'/admin/enquiries');
    }
}
