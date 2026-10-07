<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $event_type
 * @property array<string, mixed>|null $payload
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon $created_at
 */
class WebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
