<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Http\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->api(append: [SecurityHeaders::class]);

        // Sanctum SPA cookie auth for requests from the storefront origin (ARCHITECTURE §6.14).
        $middleware->statefulApi();

        $middleware->alias(['admin' => EnsureAdminAccess::class, 'idempotent' => EnsureIdempotency::class]);

        // API-only: never redirect guests/users to HTML pages.
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->redirectUsersTo(fn () => null);

        $middleware->encryptCookies(except: ['cart_token']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ApiException::class]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every API error uses the envelope from API.md §1.2.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return match (true) {
                $e instanceof ApiException => ApiResponse::error($e->getMessage(), $e->status, $e->errors, $e->errorCode),
                $e instanceof ValidationException => $e->status === 429
                    ? ApiResponse::error('Too many requests.', 429, $e->errors())
                    : ApiResponse::error('Validation failed', $e->status, $e->errors()),
                $e instanceof AuthenticationException => ApiResponse::error('Unauthenticated.', 401),
                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => ApiResponse::error('This action is unauthorized.', 403),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => ApiResponse::error('Not found.', 404),
                $e instanceof TokenMismatchException => ApiResponse::error('Session expired. Please refresh.', 419),
                $e instanceof ThrottleRequestsException => ApiResponse::error('Too many requests.', 429, headers: $e->getHeaders()),
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getStatusCode() >= 500 ? 'Something went wrong. Please try again.' : ($e->getMessage() ?: 'Request failed.'),
                    $e->getStatusCode(),
                    headers: $e->getHeaders(),
                ),
                default => config('app.debug')
                    ? null // let Laravel's debug renderer show details locally
                    : ApiResponse::error('Something went wrong. Please try again.', 500),
            };
        });
    })->create();
