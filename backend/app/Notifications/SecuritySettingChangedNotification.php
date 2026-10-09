<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerts owners/administrators when the staff two-factor requirement is switched on or off. */
class SecuritySettingChangedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly bool $enabled, public readonly string $changedBy, public readonly string $ip) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->enabled ? 'Staff two-factor sign-in turned ON' : 'Security alert: staff two-factor sign-in turned OFF')
            ->greeting($this->enabled ? 'Two-factor sign-in is on' : 'Two-factor sign-in was turned off')
            ->line(($this->enabled ? 'Staff now need' : 'Staff no longer need').' an authenticator code to open the admin.')
            ->line("Changed by {$this->changedBy} from IP {$this->ip} on ".now((string) config('commerce.store.timezone', 'UTC'))->format('j M Y, g:i A T').'.');

        if (! $this->enabled) {
            $mail->line('If this was not you, sign in, turn it back on in Settings → Security, and change your password.');
        }

        return $mail->action('Open security settings', rtrim((string) config('commerce.frontend_url'), '/').'/admin/settings');
    }
}
