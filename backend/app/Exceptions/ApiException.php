<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule failure that is safe to show to the client, carrying a
 * machine-readable `errors.code` (API.md §1.2), e.g. `two_factor_required`.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly ?string $errorCode = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function twoFactorRequired(): self
    {
        return new self('Two-factor authentication is required.', 403, 'two_factor_required');
    }

    public static function sessionExpired(): self
    {
        return new self('Your session has expired. Please sign in again.', 401, 'session_expired');
    }
}
