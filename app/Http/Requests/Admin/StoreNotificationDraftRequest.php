<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Body: { "title": "...", "message": "..." } */
class StoreNotificationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced by route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
        ];
    }
}
