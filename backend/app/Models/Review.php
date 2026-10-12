<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $product_id
 * @property int $user_id
 * @property int|null $order_item_id
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property string $status
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property Carbon|null $created_at
 */
class Review extends Model
{
    use HasPublicUuid, SoftDeletes;

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = ['product_id', 'user_id', 'order_item_id', 'rating', 'title', 'body', 'status'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'moderated_at' => 'datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ReviewPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ReviewPhoto::class)->orderBy('sort_order');
    }

    /** First name and last initial only — reviews never show a full name or email. */
    public function displayName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->user?->name)) ?: [];
        $first = $parts[0] ?? 'Customer';

        return count($parts) > 1 ? $first.' '.mb_strtoupper(mb_substr((string) end($parts), 0, 1)).'.' : $first;
    }
}
