<?php

namespace App\Http\Resources;

use App\Models\CaseMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CaseMessage */
class CaseMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'sender_id' => $this->sender_id,
            'is_mine' => $this->sender_id === $request->user('sanctum')?->user_id,
            'body' => $this->body,
            'status' => match (true) {
                $this->read_at !== null => 'read',
                $this->delivered_at !== null => 'delivered',
                default => 'sent',
            },
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
