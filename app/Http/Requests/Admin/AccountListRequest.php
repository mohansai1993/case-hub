<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string of the Clients / Lawyers list pages.
 *
 * These are GET filters typed into a page, so a bad value should just be
 * ignored (page shows everything) rather than produce an error screen. Each
 * accessor therefore sanitises its own value instead of validating up front.
 */
class AccountListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function search(): ?string
    {
        $q = $this->query('q');
        $q = is_string($q) ? trim($q) : '';

        return $q === '' ? null : mb_substr($q, 0, 100);
    }

    public function status(): ?UserStatus
    {
        return UserStatus::tryFrom((string) $this->query('status'));
    }

    public function practiceArea(): ?int
    {
        $id = $this->query('practice_area');

        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }

    /** Lawyers list only - verification is a profile field, not an account status. */
    public function verification(): ?VerificationStatus
    {
        return VerificationStatus::tryFrom((string) $this->query('verification'));
    }
}
