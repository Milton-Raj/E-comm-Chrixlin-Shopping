<?php

namespace App\Providers;

use App\Domain\Identity\PermissionCatalog;
use App\Models\User;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        $this->configureAuthorization();
        $this->configurePasswords();
        $this->configureRateLimiting();
        $this->configureAuthNotifications();

        Event::listen(Authenticated::class, fn (Authenticated $event) => Context::add('user_id', $event->user->getAuthIdentifier()));
    }

    /**
     * Permissions are checked everywhere; the only role check is super-admin here (SECURITY.md §4).
     */
    private function configureAuthorization(): void
    {
        Gate::before(fn (User $user) => $user->hasRole(PermissionCatalog::SUPER_ADMIN) ? true : null);
    }

    private function configurePasswords(): void
    {
        Password::defaults(fn () => Password::min(10)
            ->max(255)
            ->uncompromised());
    }

    /**
     * Limiters referenced by routes (API.md §1.6).
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('auth:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
        ]);

        RateLimiter::for('account', fn (Request $request) => Limit::perMinute(60)->by('account:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(90)->by('cart:'.($request->cookie('cart_token') ?: $request->ip())));
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(30)->by('checkout:'.($request->user()?->getAuthIdentifier() ?? $request->cookie('cart_token') ?? $request->ip())));
        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(30)->by('downloads:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(600)->by('webhooks:'.$request->route('provider')));

        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(300)->by('admin:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    /**
     * Verification links use the public uuid; reset links open the storefront.
     */
    private function configureAuthNotifications(): void
    {
        VerifyEmail::createUrlUsing(fn (User $user) => URL::temporarySignedRoute(
            'v1.verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $user->uuid, 'hash' => sha1($user->getEmailForVerification())],
        ));

        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('commerce.frontend_url')
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));
    }
}
