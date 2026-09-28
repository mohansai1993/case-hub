<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/** Body: { "draft_id": 1, "audience": "client|lawyer", "bulk": false, "user_ids": ["uuid", ...] } */
class SendNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission is enforced by route middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'draft_id' => ['required', 'integer', 'exists:notification_drafts,id'],
            'audience' => ['required', new Enum(UserType::class)],
            'bulk' => ['required', 'boolean'],
            'user_ids' => ['required_if:bulk,false', 'array'],
            'user_ids.*' => ['string', 'distinct', Rule::exists('users', 'user_id')],
        ];
    }

    /** Reject ids that exist but belong to the other audience (client id sent under "lawyer", etc). */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('bulk') || $validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = (array) $this->input('user_ids', []);
            $audience = UserType::tryFrom((string) $this->input('audience'));

            if ($audience === null || $ids === []) {
                return;
            }

            $matching = User::where('type', $audience->value)->whereIn('user_id', $ids)->count();

            if ($matching !== count($ids)) {
                $validator->errors()->add('user_ids', 'One or more selected recipients do not match the chosen audience.');
            }
        });
    }

    public function audience(): UserType
    {
        return UserType::from($this->input('audience'));
    }

    /** @return string[] */
    public function userIds(): array
    {
        return (array) $this->input('user_ids', []);
    }
}
