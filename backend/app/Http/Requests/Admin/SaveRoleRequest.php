<?php

namespace App\Http\Requests\Admin;

use App\Domain\Identity\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->route('role') ? 'sometimes' : 'required', 'string', 'min:2', 'max:50', 'regex:/^[\pL\pN][\pL\pN \-&]*$/u'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_keys(PermissionCatalog::permissions()))],
            'password' => ['required', 'string', 'current_password:web'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'password.current_password' => 'Your password is incorrect.',
            'permissions.required' => 'Tick at least one area this role can use.',
            'permissions.min' => 'Tick at least one area this role can use.',
            'name.regex' => 'Use letters, numbers, spaces, hyphens or &.',
        ];
    }
}
