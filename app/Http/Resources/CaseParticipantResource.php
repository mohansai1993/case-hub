<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * UserResource + online/last-seen - only exposed to the other case
 * participant (via CaseResource), never in the general lawyer directory.
 * Presence is private to people you're actually chatting with, same as
 * WhatsApp only shows last-seen to contacts.
 *
 * @mixin User
 */
class CaseParticipantResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'is_online' => $this->isOnline(),
            'last_active_at' => $this->last_active_at?->toIso8601String(),
        ]);
    }
}
