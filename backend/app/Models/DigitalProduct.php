<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-product digital delivery settings.
 *
 * @property int $product_id
 * @property int|null $download_limit
 * @property int|null $access_days
 * @property string|null $format
 */
class DigitalProduct extends Model
{
    protected $fillable = ['product_id', 'download_limit', 'access_days', 'format'];

    protected function casts(): array
    {
        return ['download_limit' => 'integer', 'access_days' => 'integer'];
    }
}
