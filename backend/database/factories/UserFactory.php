<?php

namespace Database\Factories;

use App\Domain\Identity\PermissionCatalog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /** Base32 secret used by withTwoFactor(); never use outside tests/dev. */
    public const TWO_FACTOR_TEST_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * A customer account (the role every storefront user gets on registration).
     */
    public function customer(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(PermissionCatalog::CUSTOMER));
    }

    /**
     * A staff member with the given role and confirmed 2FA (required for admin access).
     */
    public function staff(string $role = 'administrator'): static
    {
        return $this->withTwoFactor()->afterCreating(fn (User $user) => $user->assignRole($role));
    }

    /**
     * Confirmed TOTP 2FA using a known secret, so tests can generate valid codes.
     */
    public function withTwoFactor(string $secret = self::TWO_FACTOR_TEST_SECRET): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
