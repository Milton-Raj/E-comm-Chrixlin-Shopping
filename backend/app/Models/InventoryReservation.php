<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $variant_id
 * @property int $order_id
 * @property int $quantity
 * @property string $status
 * @property Carbon $expires_at
 */
class InventoryReservation extends Model
{
    protected $fillable = ['variant_id', 'order_id', 'quantity', 'status', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'quantity' => 'integer'];
    }
}
