<?php

namespace App\Http\Resources;

use App\Models\CaseAssignmentRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CaseAssignmentRequest */
class CaseAssignmentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lawyer' => new UserResource($this->whenLoaded('lawyer')),
            'status' => $this->status->value,
            'reason' => $this->reason,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
