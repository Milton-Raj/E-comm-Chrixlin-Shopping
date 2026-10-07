<?php

namespace App\Domain\Digital;

use App\Exceptions\ApiException;
use App\Models\DigitalDownload;
use App\Models\DigitalEntitlement;
use App\Models\DigitalFile;
use App\Models\DownloadToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Secure delivery (ARCHITECTURE §6.12): short-lived, single-use tokens; limits and
 * expiry checked at link creation AND at download; every attempt is logged.
 */
class DownloadService
{
    public const TOKEN_MINUTES = 5;

    /** @return array{url: string, expires_at: string} */
    public function createLink(DigitalEntitlement $entitlement, DigitalFile $file): array
    {
        $this->assertUsable($entitlement, $file);

        $token = Str::random(48);
        DownloadToken::create([
            'entitlement_id' => $entitlement->id,
            'digital_file_id' => $file->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(self::TOKEN_MINUTES),
        ]);

        return ['url' => route('v1.downloads.stream', ['token' => $token]), 'expires_at' => now()->addMinutes(self::TOKEN_MINUTES)->toIso8601String()];
    }

    /**
     * Validates and consumes a token, counting the download atomically.
     */
    public function consume(string $token, Request $request): DigitalFile
    {
        return DB::transaction(function () use ($token, $request) {
            $record = DownloadToken::query()->with(['entitlement', 'file'])
                ->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();

            if (! $record || $record->used_at !== null || $record->expires_at->isPast()) {
                abort(404);
            }

            $entitlement = $record->entitlement;
            try {
                $this->assertUsable($entitlement, $record->file);
            } catch (ApiException $e) {
                $this->log($entitlement, $record->file, $request, 'denied', $e->errorCode);
                abort(404);
            }

            $record->forceFill(['used_at' => now()])->save();
            $counted = DigitalEntitlement::query()->whereKey($entitlement->id)
                ->where(fn ($q) => $q->whereNull('download_limit')->orWhereColumn('downloads_used', '<', 'download_limit'))
                ->update(['downloads_used' => DB::raw('downloads_used + 1'), 'status' => 'downloaded']);
            if ($counted === 0) {
                abort(404);
            }

            $this->log($entitlement, $record->file, $request, 'completed');

            return $record->file;
        });
    }

    private function assertUsable(DigitalEntitlement $entitlement, DigitalFile $file): void
    {
        if ($file->product_id !== $entitlement->product_id || ! $file->is_active || $file->trashed()) {
            throw new ApiException('This file is not available.', 404, 'file_unavailable');
        }
        if (! in_array($entitlement->status, ['available', 'downloaded'], true)) {
            throw new ApiException('Access to this download has been removed.', 403, 'entitlement_revoked');
        }
        if ($entitlement->expires_at !== null && $entitlement->expires_at->isPast()) {
            throw new ApiException('Access to this download has expired.', 403, 'entitlement_expired');
        }
        if ($entitlement->download_limit !== null && $entitlement->downloads_used >= $entitlement->download_limit) {
            throw new ApiException('You have reached the download limit for this item.', 403, 'download_limit_reached');
        }
    }

    private function log(DigitalEntitlement $entitlement, DigitalFile $file, Request $request, string $status, ?string $reason = null): void
    {
        DigitalDownload::create([
            'entitlement_id' => $entitlement->id, 'digital_file_id' => $file->id, 'user_id' => $request->user()?->getKey(),
            'status' => $status, 'deny_reason' => $reason, 'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 509),
        ]);
        Log::channel('downloads')->info('Download '.$status.'.', ['entitlement' => $entitlement->uuid, 'file' => $file->uuid, 'reason' => $reason]);
    }
}
