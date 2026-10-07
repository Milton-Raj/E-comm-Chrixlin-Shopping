<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string $provider
 * @property string|null $provider_order_id
 * @property string|null $provider_payment_id
 * @property string|null $method
 * @property int $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $failure_message
 * @property Carbon|null $captured_at
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read Collection<int, PaymentTransaction> $transactions
 */
class Payment extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount' => 'integer', 'captured_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<PaymentTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
