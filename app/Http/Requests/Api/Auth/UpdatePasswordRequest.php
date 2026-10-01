<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Validation\Rules\Password;

/** Body: { "current_password", "password", "password_confirmation" } */
class UpdatePasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function password(): string
    {
        return $this->string('password')->toString();
    }
}
