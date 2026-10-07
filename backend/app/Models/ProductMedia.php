<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $uuid
 * @property int $product_id
 * @property string $disk
 * @property string $path
 * @property string|null $alt_text
 * @property int $sort_order
 */
class ProductMedia extends Model
{
    use HasPublicUuid;

    protected $fillable = ['product_id', 'disk', 'path', 'alt_text', 'sort_order'];

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
