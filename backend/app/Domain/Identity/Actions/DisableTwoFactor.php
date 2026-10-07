<?php

namespace App\Domain\Identity\Actions;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\Audit\Audit;

class DisableTwoFactor
{
    public function handle(User $user): void
    {
        // Staff must keep 2FA (SECURITY.md §3); a reset goes through another admin.
        if ($user->isStaff()) {
            throw new ApiException('Staff accounts must keep two-factor authentication enabled.', 403, 'two_factor_required_for_staff');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();

        Audit::record('user.two_factor_disabled', $user);
    }
}
