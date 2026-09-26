<?php

namespace App\Http\Requests\Api\Auth;

/** Body: { "mobile": "...", "otp": "1234", "device_name"?: "..." } */
class VerifyOtpRequest extends MobileRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'otp' => ['required', 'string', 'regex:/^[0-9]{' . config('otp.app.length') . '}$/'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'otp.required' => 'Please enter the ' . config('otp.app.length') . ' digit OTP.',
            'otp.regex' => 'Please enter the ' . config('otp.app.length') . ' digit OTP.',
        ];
    }

    public function deviceName(): string
    {
        return $this->input('device_name') ?: 'mobile';
    }
}
