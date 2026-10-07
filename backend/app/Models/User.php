<?php

namespace App\Models;

use App\Domain\Identity\PermissionCatalog;
use App\Models\Concerns\HasPublicUuid;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Customers and staff share this model; staff are users holding a non-customer role.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $locale
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_timestep
 * @property bool $is_active
 * @property bool $marketing_opt_in
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property-read int|null $orders_count
 * @property-read int|string|null $total_spent
 */
#[Fillable(['name', 'email', 'password', 'phone', 'locale', 'marketing_opt_in'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_last_used_timestep'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicUuid, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    /**
     * Mirrors the column defaults so freshly created instances are complete under strict mode.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'phone' => null,
        'locale' => 'en',
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
        'two_factor_last_used_timestep' => null,
        'is_active' => true,
        'marketing_opt_in' => false,
        'last_login_at' => null,
        'last_login_ip' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_timestep' => 'integer',
            'is_active' => 'boolean',
            'marketing_opt_in' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isStaff(): bool
    {
        return $this->getRoleNames()->contains(fn (string $name) => $name !== PermissionCatalog::CUSTOMER);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Effective permission names, including super-admin's implicit "all".
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if ($this->hasRole(PermissionCatalog::SUPER_ADMIN)) {
            return array_keys(PermissionCatalog::permissions());
        }

        return $this->getAllPermissions()->pluck('name')->sort()->values()->all();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
