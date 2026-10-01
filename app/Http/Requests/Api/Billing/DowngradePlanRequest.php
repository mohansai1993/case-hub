<?php

namespace App\Http\Requests\Api\Billing;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body: { "plan_id": 1, "confirmed": true }
 *
 * "confirmed" must be explicitly true - the app shows a warning that older
 * files will become inaccessible before calling this, and the server never
 * trusts that the warning was actually shown.
 */
class DowngradePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Please confirm that you understand older files may become inaccessible.',
        ];
    }

    public function plan(): Plan
    {
        return Plan::findOrFail($this->integer('plan_id'));
    }
}
