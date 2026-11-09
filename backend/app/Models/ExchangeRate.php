<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $base_currency
 * @property string $currency
 * @property string $rate decimal string, market rate without markup
 * @property string $source
 * @property Carbon $fetched_at
 */
class ExchangeRate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
