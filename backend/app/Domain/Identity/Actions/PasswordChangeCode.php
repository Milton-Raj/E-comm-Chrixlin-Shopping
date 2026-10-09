<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use App\Notifications\Auth\PasswordChangeCodeNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Email-confirmed password change: a 6-digit code is emailed to the account owner and must be
 * entered with the new password. Codes are stored hashed, expire after 10 minutes, allow five
 * attempts, and are single use. Sending a new code replaces the previous one.
 */
class PasswordChangeCode
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($user), ['hash' => $this->hash($code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));
        // Sent immediately (not queued): the person is waiting on the screen for it.
        $user->notifyNow(new PasswordChangeCodeNotification($code, self::TTL_MINUTES));
    }

    /** @return 'ok'|'invalid'|'expired'|'locked' */
    public function verify(User $user, #[\SensitiveParameter] string $code): string
    {
        $entry = Cache::get($this->key($user));
        if (! is_array($entry)) {
            return 'expired';
        }
        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            Cache::forget($this->key($user));

            return 'locked';
        }
        if (! hash_equals($entry['hash'], $this->hash(trim($code)))) {
            $entry['attempts']++;
            Cache::put($this->key($user), $entry, now()->addMinutes(self::TTL_MINUTES));

            return $entry['attempts'] >= self::MAX_ATTEMPTS ? 'locked' : 'invalid';
        }
        Cache::forget($this->key($user)); // single use

        return 'ok';
    }

    private function key(User $user): string
    {
        return 'identity.password_change_code.'.$user->getKey();
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
