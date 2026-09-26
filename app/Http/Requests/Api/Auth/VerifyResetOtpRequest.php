<?php

namespace App\Http\Requests\Api\Auth;

class VerifyResetOtpRequest extends ForgotPasswordRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'otp' => ['required', 'string', 'regex:/^[0-9]{' . config('otp.app.length') . '}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required' => 'Please enter the ' . config('otp.app.length') . ' digit OTP.',
            'otp.regex' => 'Please enter the ' . config('otp.app.length') . ' digit OTP.',
        ];
    }
}
