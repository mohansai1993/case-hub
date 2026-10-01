<?php

namespace App\Http\Requests\Api\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body: { "location", "years_of_experience", "practice_areas": [...], "bio"? } */
class UpdateLawyerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location' => ['required', 'string', 'max:191'],
            'years_of_experience' => ['required', 'integer', 'min:0', 'max:70'],
            'practice_areas' => ['required', 'array', 'min:1', 'max:10'],
            'practice_areas.*' => ['integer', 'distinct', Rule::exists('practice_areas', 'id')->where('is_active', true)],
            'bio' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'practice_areas.required' => 'Please select at least one practice area.',
            'practice_areas.min' => 'Please select at least one practice area.',
        ];
    }

    public function location(): string
    {
        return trim($this->string('location')->toString());
    }

    public function bio(): ?string
    {
        $value = trim((string) $this->input('bio'));

        return $value === '' ? null : $value;
    }
}
