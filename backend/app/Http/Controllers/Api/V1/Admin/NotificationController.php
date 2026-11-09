<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The signed-in staff member's own alerts (owner-scoped: nobody reads another's).
 * Polled every few seconds by the admin bell, so it never counts as activity for the
 * admin idle timeout (EnsureAdminAccess).
 */
class NotificationController extends Controller
{
    private const LIMIT = 20;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'items' => $user->notifications()->latest()->limit(self::LIMIT)->get()->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return ApiResponse::success(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(['unread_count' => 0]);
    }
}
