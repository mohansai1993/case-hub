<?php

namespace App\Http\Requests\Api\Auth;

use App\Support\Identifier;

/** Body: { "email": "user@example.com" } */
class EmailRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
        ];
    }

    public function email(): Identifier
    {
        return $this->parseIdentifier('email');
    }
}
