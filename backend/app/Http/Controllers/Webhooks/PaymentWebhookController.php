<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payments\Actions\ProcessGatewayEvent;
use App\Domain\Payments\GatewayManager;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/webhooks/payment/{provider} — authenticated, idempotent, logged, retryable (PRD §30).
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, GatewayManager $gateways, ProcessGatewayEvent $process): JsonResponse
    {
        try {
            $gateway = $gateways->get($provider);
        } catch (ApiException) {
            abort(404);
        }

        if (! $gateway->verifyWebhook($request)) {
            Log::channel('webhooks')->warning('Webhook signature rejected.', ['provider' => $provider, 'ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Invalid signature.'], 401);
        }

        $event = $gateway->parseWebhook($request);
        if (! $event) {
            return response()->json(['success' => true, 'status' => 'ignored']);
        }

        $status = $process->handle($provider, $event);

        // 5xx makes the provider retry; everything else is acknowledged.
        return response()->json(['success' => $status !== 'failed', 'status' => $status], $status === 'failed' ? 500 : 200);
    }
}
