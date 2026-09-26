<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Body of destructive account actions (suspend, reject): { "reason": "..." } */
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced by route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please give a reason.',
            'reason.min' => 'The reason must be at least 3 characters.',
            'reason.max' => 'The reason must not be longer than 500 characters.',
        ];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
