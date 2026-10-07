<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMeController extends Controller
{
    /**
     * The admin UI uses this to filter navigation. UI hiding is cosmetic; every
     * admin endpoint enforces its own permission.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return ApiResponse::success([
            'user' => new UserResource($user),
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->permissionNames(),
        ]);
    }
}
