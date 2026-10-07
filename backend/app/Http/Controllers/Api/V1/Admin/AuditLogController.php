<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $rows = AuditLog::query()->with('actor')->latest('id')->paginate(50);

        return ApiResponse::success(collect($rows->items())->map(fn (AuditLog $l) => [
            'uuid' => $l->uuid, 'action' => $l->action, 'actor' => $l->actor->name ?? 'System', 'subject' => $l->subject_type,
            'before' => $l->before, 'after' => $l->after, 'ip_address' => $l->ip_address, 'created_at' => $l->created_at->toIso8601String(),
        ]), meta: ['pagination' => ['page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total(), 'last_page' => $rows->lastPage()]]);
    }
}
