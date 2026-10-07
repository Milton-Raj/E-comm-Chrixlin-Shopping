<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\LogoutOtherSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * Always answers the same way so the endpoint cannot be used to discover accounts.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker()->sendResetLink(['email' => mb_strtolower($request->string('email')->toString())]);

        return ApiResponse::success(message: 'If an account exists for that email, a reset link has been sent.');
    }

    public function reset(ResetPasswordRequest $request, LogoutOtherSessions $logoutOtherSessions): JsonResponse
    {
        $status = Password::broker()->reset(
            [...$request->only('email', 'password', 'password_confirmation', 'token'), 'email' => mb_strtolower($request->string('email')->toString())],
            function (User $user, string $password) use ($logoutOtherSessions) {
                $user->forceFill(['password' => $password])->save();
                $logoutOtherSessions->handle($user, null);
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return ApiResponse::success(message: 'Your password has been reset. Please sign in.');
    }
}
