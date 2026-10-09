<?php

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person they were given staff access. New accounts get a one-time link to
 * choose their password (the standard reset token, so it expires like any reset link).
 */
class StaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $invitedBy,
        public readonly string $role,
        #[\SensitiveParameter] public readonly ?string $token,
    ) {
        $this->onQueue('critical');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = rtrim((string) config('commerce.frontend_url'), '/');
        $store = (string) config('commerce.store.name');
        $role = ucfirst(str_replace('-', ' ', $this->role));
        $mail = (new MailMessage)
            ->subject("You've been added to the {$store} team")
            ->greeting('Welcome to the team')
            ->line("{$this->invitedBy} gave you staff access to the {$store} admin as **{$role}**.");

        if ($this->token === null) {
            return $mail->line('Sign in with your existing password, then open the admin.')->action('Open the admin', "{$base}/admin");
        }

        $minutes = (int) config('auth.passwords.users.expire', 60);

        return $mail
            ->line('Choose your password to get started.')
            ->action('Set your password', $base.'/reset-password?'.http_build_query(['token' => $this->token, 'email' => $notifiable instanceof User ? $notifiable->email : '']))
            ->line("This link works for {$minutes} minutes. After that, use “Forgot password” on the sign-in page or ask for a new invitation.")
            ->line('Once signed in, you may be asked to set up two-factor sign-in with an authenticator app.');
    }
}
