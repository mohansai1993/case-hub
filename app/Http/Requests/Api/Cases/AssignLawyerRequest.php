<?php

namespace App\Http\Requests\Api\Cases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body: { "lawyer_id": "<uuid>" }
 *
 * Deliberately thin - only checks the lawyer_id is a real user. Domain
 * checks (is this actually a lawyer, are they verified, do they serve this
 * case's practice area, is a request already pending) live in
 * CaseAssignmentService and surface as a 422 via InvalidStateTransition.
 */
class AssignLawyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lawyer_id' => ['required', 'uuid', Rule::exists('users', 'user_id')],
        ];
    }
}
