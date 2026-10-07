<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Services\TwoFactorAuthenticator;
use App\Exceptions\ApiException;
use App\Models\User;

/**
 * Step 1 of 2FA setup: store a new unconfirmed secret and return what the
 * authenticator app needs. 2FA is not active until ConfirmTwoFactor succeeds.
 */
class EnableTwoFactor
{
    public function __construct(private readonly TwoFactorAuthenticator $twoFactor) {}

    /**
     * @return array{secret: string, otpauth_url: string, qr_svg: string}
     */
    public function handle(User $user): array
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new ApiException('Two-factor authentication is already enabled.', 409, 'two_factor_already_enabled');
        }

        $secret = $this->twoFactor->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_url' => $this->twoFactor->otpauthUrl($user, $secret),
            'qr_svg' => $this->twoFactor->qrCodeSvg($user, $secret),
        ];
    }
}
