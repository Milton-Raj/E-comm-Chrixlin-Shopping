<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only download log (PRD §17).
 *
 * @property string $status
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class DigitalDownload extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
