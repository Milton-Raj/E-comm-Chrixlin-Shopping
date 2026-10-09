<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for every /api/v1/admin route (SECURITY.md §3, §5):
 * staff only, 2FA confirmed, and an idle timeout stricter than customer sessions.
 * Per-route permissions are still enforced with `can:` middleware / policies.
 */
class EnsureAdminAccess
{
    public const LAST_ACTIVITY_KEY = 'admin.last_activity_at';

    /**
     * Admin toggle (Settings → Security), defaulting to the env value (on). The store owner
     * may switch it off in any environment; doing so needs their password, is audited and
     * alerts every owner/administrator by email (SECURITY.md §3).
     */
    public static function twoFactorRequired(): bool
    {
        return (bool) app(Settings::class)->get('security.admin_require_2fa', (bool) config('commerce.security.admin_require_2fa', true));
    }

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isStaff()) {
            Log::channel('admin')->warning('Non-staff user attempted admin access.', ['path' => $request->path()]);
            abort(403);
        }

        if (self::twoFactorRequired() && ! $user->hasTwoFactorEnabled()) {
            throw ApiException::twoFactorRequired();
        }

        if ($request->hasSession()) {
            $session = $request->session();
            $last = $session->get(self::LAST_ACTIVITY_KEY);
            $idleLimit = (int) config('commerce.security.admin_idle_minutes') * 60;

            if (is_int($last) && now()->timestamp - $last > $idleLimit) {
                Auth::guard('web')->logout();
                $session->invalidate();
                $session->regenerateToken();

                throw ApiException::sessionExpired();
            }

            $session->put(self::LAST_ACTIVITY_KEY, now()->timestamp);
        }

        return $next($request);
    }
}
