<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only stock ledger entry.
 *
 * @property int $id
 * @property int $variant_id
 * @property string $type
 * @property int $quantity
 * @property int $balance_after
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
class InventoryTransaction extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Inventory transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Inventory transactions are immutable.'));
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
