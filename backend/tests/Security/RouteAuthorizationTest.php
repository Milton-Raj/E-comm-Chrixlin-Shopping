<?php

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
| Sweep every admin and account route (SECURITY.md §5, TESTING.md security suite).
| New routes are covered automatically.
*/

function apiRoutes(string $prefix): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $r) => str_starts_with($r->uri(), $prefix))
        // Download links are owner-scoped by session OR guest order token, so guests get 404, not 401.
        ->reject(fn (RoutingRoute $r) => $r->getName() === 'v1.downloads.link')
        ->flatMap(fn (RoutingRoute $r) => collect($r->methods())
            ->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS'], true))
            ->map(fn ($m) => [$m, '/'.preg_replace('/\{[^}]+\}/', '00000000-0000-0000-0000-000000000000', $r->uri())]))
        ->values()
        ->all();
}

it('protects every admin route with the admin gate', function () {
    foreach (collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/admin')) as $route) {
        expect($route->gatherMiddleware())->toContain('auth:sanctum', 'admin');
    }
});

it('rejects guests on every admin and account route', function () {
    foreach ([...apiRoutes('api/v1/admin'), ...apiRoutes('api/v1/account'), ...apiRoutes('api/v1/me')] as [$method, $uri]) {
        $this->json($method, $uri)->assertStatus(401);
    }
});

it('rejects customers on every admin route', function () {
    $this->actingAs(User::factory()->customer()->create());

    foreach (apiRoutes('api/v1/admin') as [$method, $uri]) {
        $this->json($method, $uri)->assertStatus(403);
    }
});

it('rejects staff without 2FA on every admin route', function () {
    $this->actingAs(User::factory()->staff('order-manager')->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]));

    foreach (apiRoutes('api/v1/admin') as [$method, $uri]) {
        $this->json($method, $uri)->assertStatus(403)->assertJsonPath('errors.code', 'two_factor_required');
    }
});
