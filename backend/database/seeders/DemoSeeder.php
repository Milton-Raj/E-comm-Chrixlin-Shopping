<?php

namespace Database\Seeders;

use App\Domain\Identity\PermissionCatalog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Development-only accounts (PRD §91). Refuses to run in production.
 * The super-admin password is random and printed once, unless DEMO_PASSWORD is set locally.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder must never run in production.');
        }

        $password = config('commerce.demo_password') ?: Str::password(16, symbols: false);

        User::factory()->state(['name' => 'Demo Admin', 'email' => 'admin@example.test', 'password' => $password])
            ->staff(PermissionCatalog::SUPER_ADMIN)
            ->state(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])
            ->create();

        foreach (array_keys(PermissionCatalog::roles()) as $role) {
            if (in_array($role, [PermissionCatalog::SUPER_ADMIN, PermissionCatalog::CUSTOMER], true)) {
                continue;
            }

            User::factory()->state(['name' => Str::headline($role), 'email' => "{$role}@example.test", 'password' => $password])
                ->staff($role)
                ->state(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])
                ->create();
        }

        User::factory()->count(10)->customer()->state(['password' => $password])->create();

        $this->command->warn('Demo accounts created (development only). Password for all demo users: '.$password);
        $this->command->warn('Staff must enable 2FA from their account before using the admin API.');
    }
}
