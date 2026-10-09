<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirmation that the account password was changed, with a warning if it wasn't them. */
class PasswordChangedNotification extends Notification
{
    public function __construct(public readonly string $ip) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your password was changed')
            ->greeting('Password changed')
            ->line('The password for your account was changed on '.now((string) config('commerce.store.timezone', 'UTC'))->format('j M Y, g:i A T')." from IP {$this->ip}.")
            ->line('Other devices have been signed out.')
            ->line("If this wasn't you, reset your password straight away using “Forgot password” on the sign-in page.")
            ->action('Sign in', rtrim((string) config('commerce.frontend_url'), '/').'/login');
    }
}
