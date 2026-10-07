<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Identity\Actions\ConfirmTwoFactor;
use App\Domain\Identity\Actions\DisableTwoFactor;
use App\Domain\Identity\Actions\EnableTwoFactor;
use App\Domain\Identity\Services\TwoFactorAuthenticator;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmPasswordRequest;
use App\Http\Requests\Account\ConfirmTwoFactorRequest;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class TwoFactorController extends Controller
{
    public function store(ConfirmPasswordRequest $request, EnableTwoFactor $enable): JsonResponse
    {
        return ApiResponse::success($enable->handle($request->user()), 'Scan the QR code, then confirm with a code.');
    }

    public function confirm(ConfirmTwoFactorRequest $request, ConfirmTwoFactor $confirm): JsonResponse
    {
        $codes = $confirm->handle($request->user(), $request->string('code')->toString());

        return ApiResponse::success(['recovery_codes' => $codes], 'Two-factor authentication enabled. Store your recovery codes safely.');
    }

    public function destroy(ConfirmPasswordRequest $request, DisableTwoFactor $disable): JsonResponse
    {
        $disable->handle($request->user());

        return ApiResponse::success(message: 'Two-factor authentication disabled.');
    }

    public function recoveryCodes(ConfirmPasswordRequest $request, TwoFactorAuthenticator $twoFactor): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            throw new ApiException('Two-factor authentication is not enabled.', 409, 'two_factor_not_enabled');
        }

        $codes = $twoFactor->regenerateRecoveryCodes($user);
        Audit::record('user.two_factor_recovery_codes_regenerated', $user);

        return ApiResponse::success(['recovery_codes' => $codes]);
    }
}
