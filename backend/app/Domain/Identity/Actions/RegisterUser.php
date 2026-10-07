<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\PermissionCatalog;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string, marketing_opt_in?: bool}  $data
     */
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'marketing_opt_in' => $data['marketing_opt_in'] ?? false,
            ]);
            $user->assignRole(PermissionCatalog::CUSTOMER);

            return $user;
        });

        event(new Registered($user)); // queues the verification email

        return $user;
    }
}
