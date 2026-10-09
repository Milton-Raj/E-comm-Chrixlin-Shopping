<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\PermissionCatalog;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates, edits and deletes staff roles (Admin → Staff → Roles). The owner role and the
 * customer role are fixed; nobody grants access they lack; a role in use can't be deleted.
 */
class ManageRole
{
    public const LOCKED = [PermissionCatalog::SUPER_ADMIN, PermissionCatalog::CUSTOMER];

    /** @param  list<string>  $permissions */
    public function create(User $actor, string $label, array $permissions): Role
    {
        $name = $this->nameFor($label);
        $this->ensureCanGrant($actor, $permissions);

        $role = DB::transaction(function () use ($name, $permissions) {
            $role = new Role(['name' => $name, 'guard_name' => 'web']);
            $role->save();
            $role->syncPermissions($permissions);

            return $role;
        });
        Audit::record('role.created', $role, null, ['name' => $name, 'permissions' => $this->sorted($permissions)], $actor);

        return $role;
    }

    /** @param  list<string>  $permissions */
    public function update(User $actor, Role $role, ?string $label, array $permissions): Role
    {
        $this->ensureEditable($actor, $role);
        $this->ensureCanGrant($actor, $permissions);

        $before = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->sort()->values()->all()];
        $name = $label !== null && Str::slug($label) !== $role->name ? $this->nameFor($label) : $role->name;

        DB::transaction(function () use ($role, $name, $permissions) {
            $role->forceFill(['name' => $name])->save();
            $role->syncPermissions($permissions);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::record('role.updated', $role, $before, ['name' => $name, 'permissions' => $this->sorted($permissions)], $actor);

        return $role->refresh();
    }

    public function delete(User $actor, Role $role): void
    {
        $this->ensureEditable($actor, $role);
        $inUse = User::role($role->name)->count();
        if ($inUse > 0) {
            throw new ApiException("{$inUse} staff ".Str::plural('member', $inUse).' still '.($inUse === 1 ? 'has' : 'have').' this role. Give them another role first.', 409, 'role_in_use');
        }

        $before = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->sort()->values()->all()];
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::record('role.deleted', null, $before, null, $actor);
    }

    private function ensureEditable(User $actor, Role $role): void
    {
        if (in_array($role->name, self::LOCKED, true)) {
            throw new ApiException('The owner and customer roles can’t be changed.', 403, 'role_locked');
        }
        if ($actor->hasRole($role->name)) {
            throw new ApiException('You can’t change a role you hold yourself. Ask an owner.', 403, 'cannot_manage_own_role');
        }
    }

    /** @param  list<string>  $permissions */
    private function ensureCanGrant(User $actor, array $permissions): void
    {
        if (array_diff($permissions, $actor->permissionNames()) !== []) {
            throw new ApiException('You can only give access you have yourself.', 403, 'role_exceeds_your_access');
        }
    }

    private function nameFor(string $label): string
    {
        $name = Str::slug($label);
        if ($name === '' || in_array($name, self::LOCKED, true)) {
            throw new ApiException('Choose a different role name.', 422, 'role_name_invalid', ['name' => ['Choose a different role name.']]);
        }
        if (Role::query()->where('guard_name', 'web')->where('name', $name)->exists()) {
            throw new ApiException('A role with this name already exists.', 422, 'role_name_taken', ['name' => ['A role with this name already exists.']]);
        }

        return $name;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function sorted(array $permissions): array
    {
        sort($permissions);

        return $permissions;
    }
}
