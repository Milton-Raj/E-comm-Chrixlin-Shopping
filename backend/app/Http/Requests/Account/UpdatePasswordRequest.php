<?php

namespace App\Http\Requests\Account;

use App\Support\Auth\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', ...PasswordRules::for($this->user())],
        ];
    }
}
