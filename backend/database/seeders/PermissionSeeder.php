<?php

namespace Database\Seeders;

use App\Domain\Identity\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent; runs in every environment including production (DATABASE.md §5).
 * Existing custom role assignments made by admins are only extended, never reset,
 * except for the catalog's default roles which are synced to the catalog.
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
            Role::findOrCreate($roleName, 'web')
                ->syncPermissions(PermissionCatalog::permissionsForRole($roleName));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
