<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Domain\Identity\Actions\LogoutOtherSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmPasswordRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /**
     * Session ids are secrets and are never returned.
     */
    public function index(Request $request): JsonResponse
    {
        $currentId = $request->session()->getId();

        $sessions = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $s) => [
                'ip_address' => $s->ip_address,
                'user_agent' => $s->user_agent,
                'last_active_at' => Carbon::createFromTimestamp($s->last_activity)->toIso8601String(),
                'is_current' => hash_equals($currentId, $s->id),
            ]);

        return ApiResponse::success($sessions);
    }

    public function destroyOthers(ConfirmPasswordRequest $request, LogoutOtherSessions $logoutOtherSessions): JsonResponse
    {
        $count = $logoutOtherSessions->handle($request->user(), $request->session()->getId());

        return ApiResponse::success(['revoked' => $count], 'Signed out of all other devices.');
    }
}
