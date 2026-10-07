<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\AttemptLogin;
use App\Domain\Identity\Actions\LoginResult;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request, AttemptLogin $attemptLogin): JsonResponse
    {
        $result = $attemptLogin->handle(
            $request,
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->boolean('remember'),
        );

        if ($result === LoginResult::TwoFactorRequired) {
            return ApiResponse::success(['two_factor_required' => true]);
        }

        return ApiResponse::success(['two_factor_required' => false, 'user' => new UserResource($request->user())]);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(message: 'Signed out.');
    }
}
