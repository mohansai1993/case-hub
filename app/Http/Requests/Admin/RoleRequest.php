<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body: { "name": "...", "description": "...", "permissions": ["clients.view", ...] } */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced by route middleware.
        return true;
    }

    public function rules(): array
    {
        $current = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($current instanceof Role ? $current->id : null),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => [Rule::in(Permissions::keys())],
        ];
    }

    public function name(): string
    {
        return trim($this->string('name')->toString());
    }

    public function description(): ?string
    {
        $value = trim((string) $this->input('description'));

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        return Permissions::only((array) $this->input('permissions', []));
    }
}
