<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only gateway event record (payload stored redacted).
 *
 * @property string $type
 * @property int $amount
 * @property string $status
 */
class PaymentTransaction extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount' => 'integer'];
    }
}
