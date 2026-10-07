<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $amount
 * @property string $currency
 * @property string $reason
 * @property string $status
 * @property bool $restock
 * @property Carbon $created_at
 */
class Refund extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'restock' => 'boolean'];
    }
}
