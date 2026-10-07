<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request gets an X-Request-ID (PRD §87). A well-formed inbound id is
 * honoured so the frontend and backend logs correlate; anything else is replaced.
 * The id is put in Context so it is attached to every log record and queued job.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $requestId = preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::ulid();

        $request->headers->set(self::HEADER, $requestId);
        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
