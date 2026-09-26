<?php

namespace App\Http\Requests\Api\Auth;

use App\Rules\ValidMobile;
use App\Support\Identifier;

/** Body: { "mobile": "9876543210" } */
class MobileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:20', new ValidMobile],
        ];
    }

    public function mobile(): Identifier
    {
        return $this->parseIdentifier('mobile');
    }
}
