<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A private file; never publicly addressable (PRD §16).
 *
 * @property int $id
 * @property string $uuid
 * @property int $product_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property bool $is_active
 */
class DigitalFile extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['disk', 'path', 'checksum_sha256'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'is_active' => 'boolean'];
    }
}
