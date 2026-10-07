<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DigitalEntitlement;
use App\Support\Audit\Audit;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DigitalController extends Controller
{
    public function entitlements(Request $request): JsonResponse
    {
        $q = (string) $request->query('q', '');
        $rows = DigitalEntitlement::query()->with(['product', 'order'])
            ->when($q !== '', fn ($w) => $w->where('email', 'like', "%{$q}%"))
            ->latest('id')->paginate(25);

        return ApiResponse::success(collect($rows->items())->map(fn (DigitalEntitlement $e) => [
            'uuid' => $e->uuid, 'email' => $e->email, 'product' => $e->product?->name, 'order_number' => $e->order->order_number,
            'status' => $e->status, 'downloads_used' => $e->downloads_used, 'download_limit' => $e->download_limit,
            'expires_at' => $e->expires_at?->toIso8601String(), 'created_at' => $e->created_at->toIso8601String(),
        ]), meta: ['pagination' => ['page' => $rows->currentPage(), 'per_page' => $rows->perPage(), 'total' => $rows->total(), 'last_page' => $rows->lastPage()]]);
    }

    public function downloads(): JsonResponse
    {
        $rows = DB::table('digital_downloads')->join('digital_entitlements', 'digital_entitlements.id', '=', 'digital_downloads.entitlement_id')
            ->join('digital_files', 'digital_files.id', '=', 'digital_downloads.digital_file_id')
            ->select(['digital_downloads.status', 'digital_downloads.deny_reason', 'digital_downloads.ip_address', 'digital_downloads.created_at', 'digital_entitlements.email', 'digital_files.original_name'])
            ->orderByDesc('digital_downloads.id')->limit(100)->get();

        return ApiResponse::success($rows->map(fn ($r) => [
            'email' => $r->email, 'file' => $r->original_name, 'status' => $r->status, 'deny_reason' => $r->deny_reason,
            'ip_address' => $r->ip_address, 'created_at' => (string) $r->created_at,
        ]));
    }

    public function update(Request $request, string $entitlement): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:revoke,restore,reset']]);
        $model = DigitalEntitlement::query()->where('uuid', $entitlement)->firstOrFail();
        $before = $model->only(['status', 'downloads_used']);

        if ($data['action'] === 'restore' && $model->status === 'refunded') {
            abort(409, 'Refunded orders cannot regain access.');
        }

        $model->forceFill(match ($data['action']) {
            'revoke' => ['status' => 'revoked', 'revoked_at' => now(), 'revoke_reason' => 'Revoked by staff'],
            'restore' => ['status' => 'available', 'revoked_at' => null, 'revoke_reason' => null],
            default => ['downloads_used' => 0, 'status' => $model->status === 'downloaded' ? 'available' : $model->status],
        })->save();
        Audit::record("entitlement.{$data['action']}", $model, $before, $model->only(['status', 'downloads_used']), $request->user());

        return ApiResponse::success(message: 'Access updated.');
    }
}
