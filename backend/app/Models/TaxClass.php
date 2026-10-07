<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int $rate_bps
 * @property bool $is_default
 */
class TaxClass extends Model
{
    use HasPublicUuid;

    protected $fillable = ['name', 'slug', 'rate_bps', 'is_default'];

    protected function casts(): array
    {
        return ['rate_bps' => 'integer', 'is_default' => 'boolean'];
    }

    public static function default(): ?self
    {
        return self::query()->where('is_default', true)->first();
    }
}
