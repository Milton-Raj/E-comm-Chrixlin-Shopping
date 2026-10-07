<?php

namespace App\Domain\Identity\Actions;

use App\Models\User;

class UpdatePassword
{
    public function __construct(private readonly LogoutOtherSessions $logoutOtherSessions) {}

    public function handle(User $user, #[\SensitiveParameter] string $newPassword, ?string $currentSessionId): void
    {
        $user->forceFill(['password' => $newPassword])->save();

        $this->logoutOtherSessions->handle($user, $currentSessionId);
    }
}
