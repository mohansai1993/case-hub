<?php

namespace App\Models;

use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `Case` is a reserved word in PHP, hence `LegalCase`; the table is `cases`.
 */
class LegalCase extends Model
{
    protected $table = 'cases';

    protected $fillable = ['client_id', 'advocate_id', 'title', 'status'];

    protected function casts(): array
    {
        return ['status' => CaseStatus::class];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id', 'user_id');
    }

    public function advocate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advocate_id', 'user_id');
    }

    /** No default order: callers pick it (history wants newest-first, other uses don't care). */
    public function messages(): HasMany
    {
        return $this->hasMany(CaseMessage::class, 'case_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class, 'case_id');
    }

    public function isParticipant(User $user): bool
    {
        return $this->client_id === $user->user_id || $this->advocate_id === $user->user_id;
    }

    public function otherParty(User $user): User
    {
        return $this->client_id === $user->user_id ? $this->advocate : $this->client;
    }

    public function scopeForParticipant(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q
            ->where('client_id', $user->user_id)
            ->orWhere('advocate_id', $user->user_id));
    }
}
