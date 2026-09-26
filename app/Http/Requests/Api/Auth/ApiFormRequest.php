<?php

namespace App\Http\Requests\Api\Auth;

use App\Support\Identifier;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Only call after validation has passed. */
    protected function parseIdentifier(string $key): Identifier
    {
        return Identifier::parse($this->input($key));
    }
}
