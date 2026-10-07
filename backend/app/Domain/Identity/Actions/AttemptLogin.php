<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Credential check with progressive lockout (SECURITY.md §2). Per-minute limits
 * are applied by the `auth` route limiter; this adds the hourly per-email lockout.
 * Users with 2FA are not logged in yet: their id is parked in the session until
 * CompleteTwoFactorLogin succeeds.
 */
class AttemptLogin
{
    public const PENDING_USER_KEY = 'login.pending_user_id';

    public const PENDING_REMEMBER_KEY = 'login.pending_remember';

    public function handle(Request $request, string $email, #[\SensitiveParameter] string $password, bool $remember): LoginResult
    {
        $lockoutKey = self::lockoutKey($email);
        $maxAttempts = (int) config('commerce.security.login_lockout_attempts');

        if (RateLimiter::tooManyAttempts($lockoutKey, $maxAttempts)) {
            event(new Lockout($request));

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($lockoutKey), 'minutes' => ceil(RateLimiter::availableIn($lockoutKey) / 60)]),
            ])->status(429);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        // Hash::check runs even for unknown emails to keep timing uniform.
        $valid = Hash::check($password, $user->password ?? '$2y$12$'.str_repeat('a', 53));

        if (! $user || ! $valid || ! $user->is_active) {
            RateLimiter::hit($lockoutKey, 3600);
            event(new Failed('web', $user, ['email' => $email]));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($lockoutKey);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put([
                self::PENDING_USER_KEY => $user->getKey(),
                self::PENDING_REMEMBER_KEY => $remember,
            ]);

            return LoginResult::TwoFactorRequired;
        }

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return LoginResult::Authenticated;
    }

    public static function lockoutKey(string $email): string
    {
        return 'login-lockout:'.Str::lower(trim($email));
    }
}
