<?php

namespace Database\Seeders;

use App\Domain\Identity\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent; runs in every environment including production (DATABASE.md §5).
 * Default roles get the catalog's permissions only when they are first created: after
 * that the owner edits them in Admin → Staff → Roles, and re-seeding never undoes it.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(PermissionCatalog::permissions()) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (array_keys(PermissionCatalog::roles()) as $roleName) {
            if (Role::query()->where('name', $roleName)->where('guard_name', 'web')->exists()) {
                continue;
            }
            Role::create(['name' => $roleName, 'guard_name' => 'web'])
                ->syncPermissions(PermissionCatalog::permissionsForRole($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
