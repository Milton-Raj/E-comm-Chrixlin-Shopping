<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $type
 * @property string $name
 * @property string|null $phone
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string $state_code
 * @property string $postal_code
 * @property string $country_code
 */
class OrderAddress extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];
}
