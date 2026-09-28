<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;

class RecipientSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:client,lawyer'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function type(): UserType
    {
        return UserType::from($this->input('type'));
    }

    public function search(): ?string
    {
        $q = trim((string) $this->input('q'));

        return $q === '' ? null : $q;
    }
}
