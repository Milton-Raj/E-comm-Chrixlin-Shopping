<?php

namespace App\Domain\Orders\Enums;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Fulfilled = 'fulfilled';
    case NotRequired = 'not_required';
}
