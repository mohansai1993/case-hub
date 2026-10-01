<?php

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string of the "Client Subscriptions" list on /admin/subscriptions.
 *
 * These are GET filters typed into a page, so a bad value should just be
 * ignored (page shows everything) rather than produce an error screen.
 */
class SubscriptionListRequest extends FormRequest
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

    public function status(): ?SubscriptionStatus
    {
        return SubscriptionStatus::tryFrom((string) $this->query('status'));
    }

    public function planId(): ?int
    {
        $id = $this->query('plan');

        return is_string($id) && ctype_digit($id) ? (int) $id : null;
    }
}
