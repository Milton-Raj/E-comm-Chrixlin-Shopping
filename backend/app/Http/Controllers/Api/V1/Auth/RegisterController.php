<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle($request->validated());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return ApiResponse::success(new UserResource($user), 'Account created. Please verify your email.', status: 201);
    }
}
