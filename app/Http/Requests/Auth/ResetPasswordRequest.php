<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends IdentifierRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'password.confirmed' => 'Passwords do not match.',
        ];
    }
}
