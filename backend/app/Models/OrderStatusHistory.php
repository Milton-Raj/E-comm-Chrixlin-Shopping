<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string|null $from_status
 * @property string $to_status
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
