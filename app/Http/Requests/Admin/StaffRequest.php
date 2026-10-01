<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Models\Role;
use App\Support\Identifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/** Body: { "name", "email", "mobile", "role_id", "status", "password"?, "password_confirmation"? } */
class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced by route middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mobile' => Identifier::normalizeMobile($this->input('mobile')) ?? $this->input('mobile'),
        ]);
    }

    public function rules(): array
    {
        $current = $this->currentAdmin();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('admins', 'email')->ignore($current?->id),
            ],
            'mobile' => [
                'required', 'regex:/^[6-9]\d{9}$/',
                Rule::unique('admins', 'mobile')->ignore($current?->id),
            ],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'status' => ['required', new Enum(AdminStatus::class)],
            'password' => [
                $current ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    /**
     * The Super Admin role is never assignable here, and a disabled role can
     * only be kept, never newly assigned.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $roleId = (int) $this->input('role_id');
            $role = Role::find($roleId);
            $current = $this->currentAdmin();

            if ($role && $role->isSuperAdmin()) {
                $validator->errors()->add('role_id', 'The Super Admin role cannot be assigned here.');

                return;
            }

            if ($role && ! $role->is_active && (! $current || $current->role_id !== $roleId)) {
                $validator->errors()->add('role_id', 'This role is disabled and cannot be assigned.');
            }
        });
    }

    public function name(): string
    {
        return trim($this->string('name')->toString());
    }

    public function email(): string
    {
        return mb_strtolower(trim($this->string('email')->toString()));
    }

    public function mobile(): string
    {
        return $this->string('mobile')->toString();
    }

    public function roleId(): int
    {
        return $this->integer('role_id');
    }

    public function status(): AdminStatus
    {
        return AdminStatus::from($this->string('status')->toString());
    }

    private function currentAdmin(): ?Admin
    {
        $admin = $this->route('admin');

        return $admin instanceof Admin ? $admin : null;
    }
}
