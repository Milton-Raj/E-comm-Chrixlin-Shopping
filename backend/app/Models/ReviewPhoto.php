<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $uuid
 * @property int $review_id
 * @property string $disk
 * @property string $path
 * @property int $sort_order
 */
class ReviewPhoto extends Model
{
    use HasPublicUuid;

    protected $fillable = ['review_id', 'disk', 'path', 'sort_order'];

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
