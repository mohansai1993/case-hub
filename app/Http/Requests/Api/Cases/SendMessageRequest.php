<?php

namespace App\Http\Requests\Api\Cases;

use Illuminate\Foundation\Http\FormRequest;

/** Body: { "body": "..." } */
class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
