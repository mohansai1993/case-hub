<?php

namespace App\Http\Resources;

use App\Enums\CaseStatus;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Same shape as UserResource for a lawyer, plus a `completed_cases` count -
 * only worth computing for the single-lawyer "profile" screen, not the list.
 *
 * @mixin User
 */
class LawyerDetailResource extends UserResource
{
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        if (isset($data['lawyer'])) {
            $data['lawyer']['completed_cases'] = LegalCase::where('advocate_id', $this->user_id)
                ->where('status', CaseStatus::Closed)
                ->count();
        }

        return $data;
    }
}
