<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One fetched market rate (append-only audit trail; rows are never updated or deleted).
 *
 * @property int $id
 * @property string $base_currency
 * @property string $currency
 * @property string $rate decimal string, market rate without markup
 * @property string $source
 * @property Carbon $fetched_at
 */
class ExchangeRateHistory extends Model
{
    protected $table = 'exchange_rate_history';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
