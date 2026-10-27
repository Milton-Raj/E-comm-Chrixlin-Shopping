<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $uuid
 * @property int $product_id
 * @property string $type image|youtube
 * @property string $disk
 * @property string|null $path files only (type image)
 * @property string|null $video_id YouTube id (type youtube)
 * @property string|null $alt_text
 * @property int $sort_order
 */
class ProductMedia extends Model
{
    use HasPublicUuid;

    public const TYPE_IMAGE = 'image';

    public const TYPE_YOUTUBE = 'youtube';

    protected $fillable = ['product_id', 'type', 'disk', 'path', 'video_id', 'alt_text', 'sort_order'];

    public function url(): string
    {
        return Storage::disk($this->disk)->url((string) $this->path);
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_YOUTUBE;
    }
}
