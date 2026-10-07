<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Shipping\Actions\ApplyCourierUpdate;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/webhooks/delivery-updates — Shiprocket tracking updates.
 * Authenticated by the shared token Shiprocket sends as `x-api-key`. The path avoids the
 * words Shiprocket refuses in webhook URLs ("shiprocket", "kartrocket", "sr", "kr").
 */
class CourierWebhookController extends Controller
{
    public function __invoke(Request $request, ApplyCourierUpdate $apply): JsonResponse
    {
        $secret = (string) config('shipping.shiprocket.webhook_token');
        if ($secret === '' || ! hash_equals($secret, (string) $request->header('x-api-key'))) {
            Log::channel('webhooks')->warning('Courier webhook token rejected.', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid token.'], 401);
        }

        $status = $apply->handle((array) $request->json()->all());

        // 5xx makes Shiprocket retry; everything else is acknowledged.
        return response()->json(['success' => $status !== 'failed', 'status' => $status], $status === 'failed' ? 500 : 200);
    }
}
