<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Services\TwoFactorAuthenticator;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Validation\ValidationException;

class ConfirmTwoFactor
{
    public function __construct(private readonly TwoFactorAuthenticator $twoFactor) {}

    /**
     * @return list<string> plaintext recovery codes, shown once
     */
    public function handle(User $user, string $code): array
    {
        if ($user->two_factor_secret === null) {
            throw new ApiException('Start two-factor setup first.', 409, 'two_factor_not_started');
        }

        if ($user->hasTwoFactorEnabled()) {
            throw new ApiException('Two-factor authentication is already enabled.', 409, 'two_factor_already_enabled');
        }

        if (! $this->twoFactor->verify($user, $code)) {
            throw ValidationException::withMessages(['code' => __('The provided two-factor code is invalid.')]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $codes = $this->twoFactor->regenerateRecoveryCodes($user);

        Audit::record('user.two_factor_enabled', $user);

        return $codes;
    }
}
