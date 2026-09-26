<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'reset_token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ];
    }
}
