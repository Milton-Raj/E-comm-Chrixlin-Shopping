<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $type
 * @property string $name
 * @property string|null $company
 * @property string $email
 * @property string|null $phone
 * @property int|null $quantity
 * @property string|null $budget
 * @property string|null $occasion
 * @property Carbon|null $needed_by
 * @property string $message
 * @property string $status
 * @property string|null $notes
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Enquiry extends Model
{
    use HasPublicUuid;

    public const STATUSES = ['new', 'contacted', 'quoted', 'won', 'lost'];

    protected $fillable = ['type', 'name', 'company', 'email', 'phone', 'quantity', 'budget', 'occasion', 'needed_by', 'message', 'status', 'notes', 'updated_by', 'ip_address'];

    protected $hidden = ['ip_address'];

    protected function casts(): array
    {
        return ['needed_by' => 'date', 'quantity' => 'integer'];
    }
}
