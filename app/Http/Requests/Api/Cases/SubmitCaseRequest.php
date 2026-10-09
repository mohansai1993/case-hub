<?php

namespace App\Http\Requests\Api\Cases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body: { "title", "practice_area_id", "description", "incident_date", "location" } - all required. */
class SubmitCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'practice_area_id' => ['required', 'integer', Rule::exists('practice_areas', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'max:5000'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'location' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'practice_area_id.exists' => 'Please select a valid legal issue.',
            'incident_date.before_or_equal' => 'The incident date cannot be in the future.',
        ];
    }
}
