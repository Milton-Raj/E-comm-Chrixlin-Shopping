<?php

namespace App\Http\Requests\Admin;

use App\Domain\Identity\StaffAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'string', Rule::in(StaffAccess::staffRoles())],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['password.current_password' => 'Your password is incorrect.'];
    }
}
