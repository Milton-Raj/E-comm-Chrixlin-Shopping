<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\Auth\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', ...PasswordRules::for(User::query()->where('email', (string) $this->input('email'))->first())],
        ];
    }
}
