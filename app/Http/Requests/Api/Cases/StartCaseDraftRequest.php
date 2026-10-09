<?php

namespace App\Http\Requests\Api\Cases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body: any subset of { "title", "practice_area_id", "description", "incident_date", "location" }.
 *
 * Starts (or, called again, starts another) draft case - everything here is
 * optional because the client may call this before filling in the rest of
 * the "Create Case" screen (as soon as they pick their first evidence file,
 * so the upload has a case_id to attach to). Full validation happens at
 * SubmitCaseRequest, when the draft is finalized.
 */
class StartCaseDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'practice_area_id' => ['sometimes', 'integer', Rule::exists('practice_areas', 'id')->where('is_active', true)],
            'description' => ['sometimes', 'string', 'max:5000'],
            'incident_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'location' => ['sometimes', 'string', 'max:255'],
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
