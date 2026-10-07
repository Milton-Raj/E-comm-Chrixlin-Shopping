<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $zone
 * @property string $name
 * @property string|null $description
 * @property int $amount
 * @property int|null $free_over
 * @property int $days_min
 * @property int $days_max
 * @property bool $is_active
 */
class ShippingMethod extends Model
{
    use HasPublicUuid;

    protected $fillable = ['code', 'zone', 'name', 'description', 'amount', 'free_over', 'days_min', 'days_max', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'free_over' => 'integer', 'is_active' => 'boolean'];
    }
}
