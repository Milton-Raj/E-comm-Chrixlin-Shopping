<?php

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The 6-digit code that confirms a password change. */
class PasswordChangeCodeNotification extends Notification
{
    public function __construct(#[\SensitiveParameter] public readonly string $code, public readonly int $minutes) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->code} is your password change code")
            ->greeting('Confirm your password change')
            ->line('Enter this code to set a new password:')
            ->line("**{$this->code}**")
            ->line("It expires in {$this->minutes} minutes and can be used once.")
            ->line("If you didn't ask to change your password, ignore this email — your password stays the same — and consider changing it.");
    }
}
