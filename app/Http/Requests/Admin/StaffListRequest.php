<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string of the Staff list page.
 *
 * These are GET filters typed into a page, so a bad value should just be
 * ignored (page shows everything) rather than produce an error screen.
 */
class StaffListRequest extends FormRequest
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

    public function status(): ?AdminStatus
    {
        return AdminStatus::tryFrom((string) $this->query('status'));
    }

    public function roleId(): ?int
    {
        $id = $this->query('role');

        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }
}
