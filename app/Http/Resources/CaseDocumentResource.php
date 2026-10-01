<?php

namespace App\Http\Resources;

use App\Models\CaseDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CaseDocument */
class CaseDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'case_id' => $this->case_id,
            'uploaded_by' => $this->uploaded_by,
            'is_mine' => $this->uploaded_by === $request->user('sanctum')?->user_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'accessible' => $this->isAccessible(),
            // Not a signed URL on purpose: evidence files require the same
            // Bearer-token + case-participant check as every other endpoint,
            // not "anyone who has this link".
            'download_path' => "/api/v1/cases/{$this->case_id}/documents/{$this->id}/download",
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
