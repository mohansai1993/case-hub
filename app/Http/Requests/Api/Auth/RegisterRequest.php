<?php

namespace App\Http\Requests\Api\Auth;

use App\Rules\NotRegistered;
use App\Rules\ValidMobile;
use Illuminate\Validation\Rules\Password;

/** Fields shared by the Client and Lawyer registration forms. */
abstract class RegisterRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:191', new NotRegistered('email')],
            'mobile' => ['required', 'string', 'max:20', new ValidMobile, new NotRegistered('mobile')],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
            // Consent is recorded (terms_accepted_at), so it must be explicit.
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'terms_accepted.accepted' => 'You must agree to the Terms & Conditions and Privacy Policy.',
            'terms_accepted.required' => 'You must agree to the Terms & Conditions and Privacy Policy.',
        ];
    }
}
