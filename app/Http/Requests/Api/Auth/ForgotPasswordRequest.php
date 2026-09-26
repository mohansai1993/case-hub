<?php

namespace App\Http\Requests\Api\Auth;

use App\Rules\ValidIdentifier;
use App\Support\Identifier;

/** Body: { "identifier": "email or mobile" } */
class ForgotPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:191', new ValidIdentifier],
        ];
    }

    public function identifier(): Identifier
    {
        return $this->parseIdentifier('identifier');
    }
}
