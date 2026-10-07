<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Identity\Actions\CompleteTwoFactorLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Resources\UserResource;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class TwoFactorChallengeController extends Controller
{
    public function __invoke(TwoFactorChallengeRequest $request, CompleteTwoFactorLogin $complete): JsonResponse
    {
        $user = $complete->handle($request, $request->input('code'), $request->input('recovery_code'));

        return ApiResponse::success(['two_factor_required' => false, 'user' => new UserResource($user)]);
    }
}
