<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Identity\Actions\PasswordChangeCode;
use App\Domain\Identity\Actions\UpdatePassword;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Notifications\Auth\PasswordChangedNotification;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Password change confirmed by a code emailed to the account owner (instead of the current password). */
class PasswordCodeController extends Controller
{
    public function send(Request $request, PasswordChangeCode $codes): JsonResponse
    {
        $user = $request->user();
        $codes->send($user);
        Audit::record('account.password_code_sent', $user, null, null, $user);

        return ApiResponse::success(['email' => $this->mask($user->email), 'expires_in_minutes' => PasswordChangeCode::TTL_MINUTES],
            'We sent a 6-digit code to '.$this->mask($user->email).'.');
    }

    public function update(Request $request, PasswordChangeCode $codes, UpdatePassword $updatePassword): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\s*\d{6}\s*$/'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);
        $user = $request->user();

        match ($codes->verify($user, $data['code'])) {
            'ok' => null,
            'invalid' => throw new ApiException('That code is not right. Check the email and try again.', 422, 'code_invalid', ['code' => ['That code is not right.']]),
            'locked' => throw new ApiException('Too many wrong codes. Request a new code.', 429, 'code_locked', ['code' => ['Too many wrong codes. Request a new code.']]),
            default => throw new ApiException('This code has expired. Request a new code.', 422, 'code_expired', ['code' => ['This code has expired. Request a new code.']]),
        };

        $updatePassword->handle($user, $data['password'], $request->session()->getId());
        Audit::record('account.password_changed', $user, null, ['via' => 'email_code'], $user);
        $user->notifyNow(new PasswordChangedNotification((string) $request->ip()));

        return ApiResponse::success(message: 'Password changed. Other devices have been signed out.');
    }

    /** m*****@gmail.com — shows where the code went without printing the full address. */
    private function mask(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2) + [1 => ''];

        return mb_substr($local, 0, 1).str_repeat('*', max(1, mb_strlen($local) - 1)).'@'.$domain;
    }
}
