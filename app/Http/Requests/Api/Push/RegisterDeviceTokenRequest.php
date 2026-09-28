<?php

namespace App\Http\Requests\Api\Push;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/** Body: { "token": "...", "platform": "android|ios|web", "device_name": "Pixel 8" } */
class RegisterDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', new Enum(DevicePlatform::class)],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
