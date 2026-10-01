<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;

/** Body: multipart/form-data, field "photo". */
class UpdatePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // "JPG or PNG, max 5MB" - same rule as the lawyer registration photo.
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:' . config('casehub.profile_photo.max_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.max' => 'The profile photo must not be larger than 5MB.',
            'photo.mimes' => 'The profile photo must be a JPG or PNG image.',
        ];
    }
}
