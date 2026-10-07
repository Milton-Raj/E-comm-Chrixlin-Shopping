<?php

namespace App\Support\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

/**
 * Monolog tap that scrubs secrets from log context/extra before anything is written
 * (SECURITY.md §9). Applied to every file channel in config/logging.php.
 */
class RedactSensitiveData
{
    public const REDACTED = '[REDACTED]';

    /**
     * Keys are matched case-insensitively as substrings, so `x_api_key`,
     * `newPassword` and `Authorization` are all caught.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password', 'secret', 'token', 'authorization', 'cookie', 'card', 'cvv', 'cvc',
        'otp', 'api_key', 'apikey', 'recovery_code', 'two_factor', 'signature',
    ];

    /**
     * Short keys matched exactly, as substring matching would over-match ("pan" in "company").
     *
     * @var list<string>
     */
    private const EXACT_KEYS = ['pan', 'pin'];

    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof MonologLogger) {
            $monolog->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
                context: self::redact($record->context),
                extra: self::redact($record->extra),
            ));
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data, int $depth = 0): array
    {
        if ($depth > 8) {
            return [self::REDACTED];
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value, $depth + 1);
            }
        }

        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $key = strtolower($key);

        if (in_array($key, self::EXACT_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
