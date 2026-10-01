<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseDocument extends Model
{
    protected $fillable = ['case_id', 'uploaded_by', 'path', 'original_name', 'mime_type', 'size_bytes'];

    protected function casts(): array
    {
        return ['inaccessible_at' => 'datetime'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }

    public function isAccessible(): bool
    {
        return $this->inaccessible_at === null;
    }

    public function scopeAccessible(Builder $query): Builder
    {
        return $query->whereNull('inaccessible_at');
    }
}
