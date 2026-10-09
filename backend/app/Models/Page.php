<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * CMS page. `body` is plain text with blank-line paragraphs (rendered escaped; no HTML stored).
 *
 * @property int $id
 * @property string $uuid
 * @property string $slug
 * @property string $title
 * @property string|null $body
 * @property string $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon $updated_at
 */
class Page extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $fillable = ['slug', 'title', 'body', 'status', 'seo_title', 'seo_description', 'published_at'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
