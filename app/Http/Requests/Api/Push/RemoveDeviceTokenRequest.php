<?php

namespace App\Http\Requests\Api\Push;

use Illuminate\Foundation\Http\FormRequest;

/** Body: { "token": "..." } */
class RemoveDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }
}
