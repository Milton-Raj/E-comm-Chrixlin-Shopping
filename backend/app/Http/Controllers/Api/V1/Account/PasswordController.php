<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Identity\Actions\UpdatePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, UpdatePassword $updatePassword): JsonResponse
    {
        $updatePassword->handle($request->user(), $request->string('password')->toString(), $request->session()->getId());

        return ApiResponse::success(message: 'Password updated. Other devices have been signed out.');
    }
}
