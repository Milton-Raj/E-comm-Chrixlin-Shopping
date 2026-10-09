<?php

namespace App\Domain\Identity;

use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Shared rules for staff management (SECURITY.md §4): nobody changes their own
 * staff access, and only a super-admin may grant, change or remove super-admin.
 */
final class StaffAccess
{
    /** @return list<string> every role that can be given to a staff member */
    public static function staffRoles(): array
    {
        return Role::query()->where('guard_name', 'web')->where('name', '!=', PermissionCatalog::CUSTOMER)
            ->orderBy('name')->pluck('name')->all();
    }

    public static function staffRoleOf(User $user): ?string
    {
        return $user->getRoleNames()->first(fn (string $name) => $name !== PermissionCatalog::CUSTOMER);
    }

    public static function ensureCanManage(User $actor, User $target, ?string $newRole = null): void
    {
        if ($actor->is($target)) {
            throw new ApiException('You can’t change your own staff access. Ask another owner or administrator.', 403, 'cannot_manage_self');
        }

        self::ensureCanGrant($actor, $newRole);

        if ($target->hasRole(PermissionCatalog::SUPER_ADMIN) && ! $actor->hasRole(PermissionCatalog::SUPER_ADMIN)) {
            throw new ApiException('Only an owner (super admin) can change another owner’s account.', 403, 'super_admin_only');
        }
    }

    public static function ensureCanGrant(User $actor, ?string $role): void
    {
        if ($role === PermissionCatalog::SUPER_ADMIN && ! $actor->hasRole(PermissionCatalog::SUPER_ADMIN)) {
            throw new ApiException('Only an owner (super admin) can make someone an owner.', 403, 'super_admin_only');
        }
    }

    /** Ends every session of the user, so a removed role or deactivation applies at once. */
    public static function signOutEverywhere(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        $user->tokens()->delete();
    }
}
