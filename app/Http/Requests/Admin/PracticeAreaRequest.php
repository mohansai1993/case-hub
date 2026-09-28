<?php

namespace App\Http\Requests\Admin;

use App\Models\PracticeArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Body: { "name": "..." } */
class PracticeAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced by route middleware.
        return true;
    }

    public function rules(): array
    {
        $current = $this->route('practiceArea');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('practice_areas', 'name')->ignore($current instanceof PracticeArea ? $current->id : null),
            ],
        ];
    }

    public function name(): string
    {
        return trim($this->string('name')->toString());
    }
}
