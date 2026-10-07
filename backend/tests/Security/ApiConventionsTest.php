<?php

use App\Http\Middleware\AssignRequestId;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\Audit;

it('returns the error envelope with a request id for unknown routes', function () {
    $response = $this->getJson('/api/v1/does-not-exist');

    expect($response)->toBeApiError(404);
    expect($response->json('meta.request_id'))->toBe($response->headers->get(AssignRequestId::HEADER));
});

it('honours a well-formed inbound request id and replaces malformed ones', function () {
    $this->withHeader('X-Request-ID', 'abc12345-req')->getJson('/api/v1/health')
        ->assertHeader('X-Request-ID', 'abc12345-req');

    $replaced = $this->withHeader('X-Request-ID', '<script>')->getJson('/api/v1/health')->headers->get('X-Request-ID');
    expect($replaced)->not->toBe('<script>')->and(strlen($replaced))->toBe(26);
});

it('sends security headers on API responses', function () {
    $this->getJson('/api/v1/health')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
});

it('allows CORS with credentials only for the storefront origin', function () {
    $this->withHeaders(['Origin' => 'http://localhost:3000', 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/v1/auth/login')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');

    $evil = $this->withHeaders(['Origin' => 'https://evil.example', 'Access-Control-Request-Method' => 'POST'])
        ->options('/api/v1/auth/login');
    // A single allowed origin is always echoed; browsers reject the mismatch for other origins.
    expect($evil->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example');
});

it('allows the custom headers the storefront sends in preflight requests', function () {
    $allowed = strtolower((string) $this->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'content-type,x-xsrf-token,x-request-id,idempotency-key,x-order-token',
    ])->options('/api/v1/orders/ORD-1/payment/confirm')->headers->get('Access-Control-Allow-Headers'));

    foreach (['x-order-token', 'idempotency-key', 'x-xsrf-token', 'x-request-id'] as $header) {
        expect($allowed)->toContain($header);
    }
});

it('hides exception details in production-like mode', function () {
    Route::get('/api/v1/__boom', fn () => throw new RuntimeException('secret internals'));

    $response = $this->getJson('/api/v1/__boom');

    expect($response)->toBeApiError(500);
    $response->assertJsonPath('message', 'Something went wrong. Please try again.');
    expect($response->getContent())->not->toContain('secret internals');
});

it('stores only changed fields in the audit log and refuses edits', function () {
    $user = User::factory()->create();

    $log = Audit::record('product.updated', $user, ['price' => 2999, 'name' => 'Tee'], ['price' => 2499, 'name' => 'Tee', 'password' => 'x']);

    expect($log->before)->toBe(['price' => 2999])
        ->and($log->after)->toBe(['price' => 2499, 'password' => '[REDACTED]'])
        ->and($log->actor_type)->toBe('system');

    expect(fn () => $log->update(['action' => 'tampered']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
    expect(AuditLog::count())->toBe(1);
});
