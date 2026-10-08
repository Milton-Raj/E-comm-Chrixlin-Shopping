<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A GST tax invoice for a paid order. Financial record: never deleted.
 *
 * @property int $id
 * @property string $uuid
 * @property int $order_id
 * @property string $invoice_number
 * @property string $financial_year
 * @property int $sequence
 * @property array{legal_name: string, gstin: ?string, address: ?string, state_code: string, email: ?string} $seller
 * @property Carbon $issued_at
 * @property Carbon|null $emailed_at
 * @property-read Order $order
 */
class Invoice extends Model
{
    use HasPublicUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seller' => 'array', 'issued_at' => 'datetime', 'emailed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Invoices are financial records and cannot be deleted.'));
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
