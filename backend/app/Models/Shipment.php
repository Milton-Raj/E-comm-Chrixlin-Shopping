<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string|null $provider
 * @property string|null $provider_order_id
 * @property string|null $provider_shipment_id
 * @property string|null $carrier
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property string|null $status
 * @property string|null $courier_status
 * @property Carbon|null $pickup_scheduled_at
 * @property string|null $last_error
 * @property Carbon|null $last_event_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property-read Order $order
 */
class Shipment extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'order_id', 'provider', 'provider_order_id', 'provider_shipment_id', 'carrier', 'tracking_number', 'tracking_url',
        'status', 'courier_status', 'pickup_scheduled_at', 'last_error', 'last_event_at', 'shipped_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'delivered_at' => 'datetime', 'pickup_scheduled_at' => 'datetime', 'last_event_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
