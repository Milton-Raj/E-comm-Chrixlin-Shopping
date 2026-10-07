<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Log out all other devices" (PRD §62): removes the user's other database sessions
 * and rotates the remember token so "remember me" cookies stop working.
 */
class LogoutOtherSessions
{
    public function handle(User $user, ?string $currentSessionId): int
    {
        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($currentSessionId, fn ($q) => $q->where('id', '!=', $currentSessionId))
            ->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->save();

        Log::channel('auth')->info('Other sessions revoked.', ['user_id' => $user->getKey(), 'count' => $deleted]);

        return $deleted;
    }
}
