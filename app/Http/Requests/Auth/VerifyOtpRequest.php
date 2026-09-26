<?php

namespace App\Http\Requests\Auth;

class VerifyOtpRequest extends IdentifierRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'otp' => ['required', 'string', 'regex:/^\d{' . config('otp.length') . '}$/'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'otp.required' => 'Please enter the ' . config('otp.length') . ' digit OTP.',
            'otp.regex' => 'Please enter the ' . config('otp.length') . ' digit OTP.',
        ];
    }
}
