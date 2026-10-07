<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string|null $description
 * @property string $type
 * @property int $value
 * @property int|null $max_discount
 * @property int|null $min_order_total
 * @property bool $first_order_only
 * @property int|null $usage_limit
 * @property int $usage_count
 * @property int|null $per_customer_limit
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 */
class Coupon extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = ['code', 'description', 'type', 'value', 'max_discount', 'min_order_total', 'first_order_only', 'usage_limit', 'per_customer_limit', 'starts_at', 'ends_at', 'is_active'];

    protected $attributes = ['usage_count' => 0];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'max_discount' => 'integer',
            'min_order_total' => 'integer',
            'first_order_only' => 'boolean',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'per_customer_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }
}
