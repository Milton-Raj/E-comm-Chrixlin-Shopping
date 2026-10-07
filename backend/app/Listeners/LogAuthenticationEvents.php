<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

/**
 * Security events → `auth` log channel (SECURITY.md §9). Emails are logged for
 * failures only, to support lockout investigations; passwords never.
 * Registered automatically by Laravel's listener discovery (handle* methods).
 */
class LogAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => request()->ip()])->saveQuietly();
        }

        Log::channel('auth')->info('Login succeeded.', ['user_id' => $user->getAuthIdentifier(), 'ip' => request()->ip(), 'remember' => $event->remember]);
    }

    public function handleFailed(Failed $event): void
    {
        Log::channel('auth')->warning('Login failed.', ['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()]);
    }

    public function handleLockout(Lockout $event): void
    {
        Log::channel('auth')->warning('Login locked out.', ['email' => $event->request->input('email'), 'ip' => $event->request->ip()]);
    }

    public function handleLogout(Logout $event): void
    {
        Log::channel('auth')->info('Logout.', ['user_id' => $event->user->getAuthIdentifier()]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        Log::channel('auth')->notice('Password reset.', ['user_id' => $event->user->getAuthIdentifier()]);
    }

    public function handleRegistered(Registered $event): void
    {
        Log::channel('auth')->info('User registered.', ['user_id' => $event->user->getAuthIdentifier()]);
    }

    public function handleVerified(Verified $event): void
    {
        Log::channel('auth')->info('Email verified.', ['user_id' => $event->user instanceof User ? $event->user->getKey() : null]);
    }
}
