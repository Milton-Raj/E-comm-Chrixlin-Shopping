<?php

namespace App\Domain\Identity\Actions;

enum LoginResult: string
{
    case Authenticated = 'authenticated';
    case TwoFactorRequired = 'two_factor_required';
}
