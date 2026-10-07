<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable store setting. Secrets never live here (SECURITY.md §8).
 *
 * @property string $group
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'is_encrypted', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_encrypted' => 'boolean',
        ];
    }
}
