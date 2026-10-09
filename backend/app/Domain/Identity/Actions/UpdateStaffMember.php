<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\PermissionCatalog;
use App\Domain\Identity\StaffAccess;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;

/** Changes a staff member's role, deactivates or reactivates them, or resets their 2FA. */
class UpdateStaffMember
{
    public function handle(User $actor, User $user, ?string $role, ?bool $active): User
    {
        $this->ensureStaff($user);
        StaffAccess::ensureCanManage($actor, $user, $role);

        $deactivated = DB::transaction(function () use ($actor, $user, $role, $active): bool {
            $current = StaffAccess::staffRoleOf($user);

            if ($role !== null && $role !== $current) {
                $keep = $user->hasRole(PermissionCatalog::CUSTOMER) ? [PermissionCatalog::CUSTOMER] : [];
                $user->syncRoles([...$keep, $role]);
                Audit::record('staff.role_changed', $user, ['role' => $current], ['role' => $role], $actor);
            }

            if ($active !== null && $active !== $user->is_active) {
                $user->forceFill(['is_active' => $active])->save();
                Audit::record($active ? 'staff.reactivated' : 'staff.deactivated', $user, ['is_active' => ! $active], ['is_active' => $active], $actor);

                return ! $active;
            }

            return false;
        });

        // Permissions are read on every request, so a role change applies at once; a
        // deactivated person is also signed out of every device.
        if ($deactivated) {
            StaffAccess::signOutEverywhere($user);
        }

        return $user->refresh();
    }

    public function resetTwoFactor(User $actor, User $user): void
    {
        $this->ensureStaff($user);
        StaffAccess::ensureCanManage($actor, $user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_used_timestep' => null,
        ])->save();
        StaffAccess::signOutEverywhere($user);

        Audit::record('staff.two_factor_reset', $user, null, null, $actor);
    }

    private function ensureStaff(User $user): void
    {
        if (! $user->isStaff()) {
            throw new ApiException('This person is not a staff member.', 404, 'not_staff');
        }
    }
}
