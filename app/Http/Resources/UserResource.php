<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'type' => $this->type->value,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'image_url' => $this->imageUrl(),
            'email_verified' => $this->hasVerifiedEmail(),
            'status' => $this->status->value,
            'lawyer' => $this->when($this->isLawyer(), fn () => $this->lawyerDetails()),
        ];
    }

    private function lawyerDetails(): ?array
    {
        $profile = $this->lawyerProfile;

        if (! $profile) {
            return null;
        }

        return [
            'location' => $profile->location,
            'years_of_experience' => $profile->years_of_experience,
            'bio' => $profile->bio,
            'verification_status' => $profile->verification_status->value,
            'practice_areas' => $this->practiceAreas->map(fn ($area) => [
                'id' => $area->id,
                'name' => $area->name,
            ])->values(),
        ];
    }
}
