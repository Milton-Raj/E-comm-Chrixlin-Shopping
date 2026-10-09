<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Validation\Rules\Password;

/**
 * Customers choose any password of 6+ characters (owner decision 2026-10-09: no complexity
 * or breach checks at sign-up, so nobody is turned away at checkout). Staff, who can see
 * orders and customer data, keep Password::defaults() (10+ characters, breach-checked).
 */
final class PasswordRules
{
    public const CUSTOMER_MIN = 6;

    /** @return list<mixed> */
    public static function for(?User $user): array
    {
        return $user?->isStaff() ? [Password::defaults()] : [Password::min(self::CUSTOMER_MIN)->max(255)];
    }
}
