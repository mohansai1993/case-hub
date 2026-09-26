<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Validation\Rule;

class RegisterLawyerRequest extends RegisterRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            // "JPG or PNG, max 5MB"
            'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:' . config('casehub.profile_photo.max_kb')],
            'location' => ['required', 'string', 'max:191'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:70'],
            'practice_areas' => ['required', 'array', 'min:1', 'max:10'],
            'practice_areas.*' => ['integer', 'distinct', Rule::exists('practice_areas', 'id')->where('is_active', true)],
            'bio' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'photo.max' => 'The profile photo must not be larger than 5MB.',
            'photo.mimes' => 'The profile photo must be a JPG or PNG image.',
            'practice_areas.required' => 'Please select at least one practice area.',
            'practice_areas.min' => 'Please select at least one practice area.',
        ];
    }
}
