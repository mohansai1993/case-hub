<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanStorageUnit;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Body: { "name", "storage_amount", "storage_unit", "price", "description"?, "is_popular"? } */
class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced by route middleware.
        return true;
    }

    public function rules(): array
    {
        $current = $this->route('plan');

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('plans', 'name')->ignore($current instanceof Plan ? $current->id : null),
            ],
            'storage_amount' => ['required', 'integer', 'min:1'],
            'storage_unit' => ['required', new Enum(PlanStorageUnit::class)],
            'price' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_popular' => ['sometimes', 'boolean'],
        ];
    }

    public function name(): string
    {
        return trim($this->string('name')->toString());
    }

    public function description(): ?string
    {
        $value = trim((string) $this->input('description'));

        return $value === '' ? null : $value;
    }

    public function storageUnit(): PlanStorageUnit
    {
        return PlanStorageUnit::from($this->string('storage_unit')->toString());
    }

    public function isPopular(): bool
    {
        return $this->boolean('is_popular');
    }
}
