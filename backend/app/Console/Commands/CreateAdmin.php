<?php

namespace App\Console\Commands;

use App\Domain\Identity\PermissionCatalog;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the first super-admin in production (DEPLOYMENT.md §9). The generated
 * password is shown once; 2FA must be set up before the admin API is usable.
 */
#[Signature('app:create-admin {email} {--name=Administrator}')]
#[Description('Create a super-admin account with a random password')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');

            return self::FAILURE;
        }

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->error('A user with that email already exists.');

            return self::FAILURE;
        }

        $password = Str::password(20);

        $user = DB::transaction(function () use ($email, $password) {
            $user = User::create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(PermissionCatalog::SUPER_ADMIN);
            Audit::record('user.super_admin_created', $user);

            return $user;
        });

        $this->info("Super-admin {$user->email} created.");
        $this->warn("Temporary password (shown once): {$password}");
        $this->warn('Sign in, change the password and enable two-factor authentication.');

        return self::SUCCESS;
    }
}
