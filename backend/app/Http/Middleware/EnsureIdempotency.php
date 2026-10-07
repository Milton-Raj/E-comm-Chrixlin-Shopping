<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key support for money-moving POSTs (API.md §1.4): the same key and
 * body replays the stored response; the same key with a different body is rejected.
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key');
        if (! preg_match('/^[A-Za-z0-9-]{8,64}$/', $key)) {
            throw new ApiException('A valid Idempotency-Key header is required.', 400, 'idempotency_key_required');
        }

        $scope = $request->route()?->getName().'|'.($request->user()?->getAuthIdentifier() ?? $request->cookie('cart_token') ?? $request->ip());
        $hash = hash('sha256', $request->getContent());

        $existing = DB::table('idempotency_keys')->where('key', $key)->where('scope', $scope)->first();
        if ($existing) {
            if (! hash_equals($existing->request_hash, $hash)) {
                throw new ApiException('This Idempotency-Key was already used with a different request.', 400, 'idempotency_key_reused');
            }
            if ($existing->response_status === null) {
                throw new ApiException('This request is already being processed.', 409, 'request_in_progress');
            }

            return new JsonResponse(json_decode((string) $existing->response_body, true), (int) $existing->response_status, ['Idempotent-Replayed' => 'true']);
        }

        DB::table('idempotency_keys')->insert(['key' => $key, 'scope' => $scope, 'request_hash' => $hash, 'expires_at' => now()->addDay(), 'created_at' => now()]);

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            DB::table('idempotency_keys')->where('key', $key)->where('scope', $scope)
                ->update(['response_status' => $response->getStatusCode(), 'response_body' => $response->getContent()]);
        } else {
            DB::table('idempotency_keys')->where('key', $key)->where('scope', $scope)->delete();
        }

        return $response;
    }
}
