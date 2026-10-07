<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $entitlement_id
 * @property int $digital_file_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property-read DigitalEntitlement $entitlement
 * @property-read DigitalFile $file
 */
class DownloadToken extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    /** @return BelongsTo<DigitalEntitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(DigitalEntitlement::class, 'entitlement_id');
    }

    /** @return BelongsTo<DigitalFile, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(DigitalFile::class, 'digital_file_id');
    }
}
