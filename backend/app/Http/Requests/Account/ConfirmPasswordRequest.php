<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Re-authentication for sensitive account actions (SECURITY.md §2).
 */
class ConfirmPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'current_password:web']];
    }
}
