<?php

namespace App\Http\Requests\Api\Billing;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body: { "plan_id": 2 } - used for subscribe and upgrade. */
class ChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
        ];
    }

    public function plan(): Plan
    {
        return Plan::findOrFail($this->integer('plan_id'));
    }
}
