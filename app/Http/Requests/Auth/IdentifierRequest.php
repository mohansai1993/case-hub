<?php

namespace App\Http\Requests\Auth;

use App\Rules\ValidIdentifier;
use App\Support\Identifier;
use Illuminate\Foundation\Http\FormRequest;

/** Base for the forgot-password steps, all of which start from an identifier. */
abstract class IdentifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:191', new ValidIdentifier],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Please enter a valid email or 10 digit mobile number.',
        ];
    }

    /** Only call after validation has passed. */
    public function identifier(): Identifier
    {
        return Identifier::parse($this->input('identifier'));
    }
}
