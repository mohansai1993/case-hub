<?php

namespace App\Http\Resources;

use App\Models\LegalCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `unread_count` is expected to already be on the model (via `withCount` in
 * the controller, aliased per-viewer) - computing it here per row would be
 * an N+1 query on the case list.
 *
 * @mixin LegalCase
 */
class CaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'client' => new UserResource($this->whenLoaded('client')),
            'advocate' => new UserResource($this->whenLoaded('advocate')),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
