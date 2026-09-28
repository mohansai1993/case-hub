<?php

namespace App\Http\Requests\Api\Cases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body: { "advocate_id": "uuid", "title": "..." } */
class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'advocate_id' => ['required', 'string', Rule::exists('users', 'user_id')->where('type', 'lawyer')],
            'title' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'advocate_id.exists' => 'Please select a valid lawyer.',
        ];
    }
}
