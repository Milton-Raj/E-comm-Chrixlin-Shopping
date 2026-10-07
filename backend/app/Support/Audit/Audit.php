<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Support\Logging\RedactSensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Records sensitive actions (ARCHITECTURE §6.18). Only changed fields are stored,
 * and values pass through the same redaction as logs.
 */
class Audit
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?Model $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        if ($before !== null && $after !== null) {
            [$before, $after] = self::diff($before, $after);
        }

        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'actor_type' => $actor ? 'user' : 'system',
            'action' => $action,
            'subject_type' => $subject ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before === null ? null : RedactSensitiveData::redact($before),
            'after' => $after === null ? null : RedactSensitiveData::redact($after),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 509) : null,
            'request_id' => Context::get('request_id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private static function diff(array $before, array $after): array
    {
        $changedKeys = array_keys(array_filter(
            $after,
            fn ($value, $key) => ! array_key_exists($key, $before) || $before[$key] !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));
        $removedKeys = array_diff(array_keys($before), array_keys($after));
        $keys = array_flip([...$changedKeys, ...$removedKeys]);

        return [array_intersect_key($before, $keys), array_intersect_key($after, $keys)];
    }
}
