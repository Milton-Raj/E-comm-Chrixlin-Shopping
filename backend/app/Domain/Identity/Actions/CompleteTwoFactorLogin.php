<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Services\TwoFactorAuthenticator;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompleteTwoFactorLogin
{
    public function __construct(private readonly TwoFactorAuthenticator $twoFactor) {}

    public function handle(Request $request, ?string $code, ?string $recoveryCode): User
    {
        $userId = $request->session()->get(AttemptLogin::PENDING_USER_KEY);
        $user = $userId ? User::query()->whereKey($userId)->where('is_active', true)->first() : null;

        if (! $user) {
            throw new ApiException('Your sign-in attempt expired. Please sign in again.', 401, 'login_expired');
        }

        $valid = $code !== null
            ? $this->twoFactor->verify($user, $code)
            : ($recoveryCode !== null && $this->twoFactor->useRecoveryCode($user, $recoveryCode));

        if (! $valid) {
            Log::channel('auth')->warning('Two-factor challenge failed.', ['user_id' => $user->getKey()]);

            throw ValidationException::withMessages([
                $code !== null ? 'code' : 'recovery_code' => __('The provided two-factor code is invalid.'),
            ]);
        }

        if ($recoveryCode !== null) {
            Log::channel('auth')->notice('Two-factor recovery code used.', ['user_id' => $user->getKey()]);
        }

        $remember = (bool) $request->session()->pull(AttemptLogin::PENDING_REMEMBER_KEY, false);
        $request->session()->forget(AttemptLogin::PENDING_USER_KEY);

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return $user;
    }
}
