<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Domain\Digital\DownloadService;
use App\Http\Controllers\Controller;
use App\Http\Resources\EntitlementResource;
use App\Models\DigitalEntitlement;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $entitlements = DigitalEntitlement::query()->where('user_id', $request->user()->getKey())
            ->with(['product.files', 'order'])->latest('id')->get();

        return ApiResponse::success(EntitlementResource::collection($entitlements));
    }

    public function link(Request $request, string $entitlement, string $file, DownloadService $downloads): JsonResponse
    {
        $record = DigitalEntitlement::query()->where('uuid', $entitlement)->firstOrFail();
        $this->authorizeOwner($request, $record);
        $digitalFile = $record->product?->files()->where('uuid', $file)->firstOrFail() ?? abort(404);

        return ApiResponse::success($downloads->createLink($record, $digitalFile));
    }

    public function stream(Request $request, string $token, DownloadService $downloads): StreamedResponse
    {
        $file = $downloads->consume($token, $request);

        return Storage::disk($file->disk)->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeOwner(Request $request, DigitalEntitlement $entitlement): void
    {
        $userId = $request->user()?->getKey();
        if ($userId !== null && $entitlement->user_id === $userId) {
            return;
        }

        $token = (string) $request->header('X-Order-Token');
        if ($token !== '' && DB::table('order_access_tokens')->where('order_id', $entitlement->order_id)
            ->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->exists()) {
            return;
        }

        abort(404);
    }
}
