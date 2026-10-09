<?php

namespace App\Models;

use App\Enums\CaseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * `Case` is a reserved word in PHP, hence `LegalCase`; the table is `cases`.
 */
class LegalCase extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'client_id', 'advocate_id', 'title', 'status',
        'practice_area_id', 'description', 'incident_date', 'location', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CaseStatus::class,
            'incident_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id', 'user_id');
    }

    public function advocate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advocate_id', 'user_id');
    }

    public function practiceArea(): BelongsTo
    {
        return $this->belongsTo(PracticeArea::class);
    }

    /** True until the client taps "Create Case" - invisible everywhere except the intake screen itself. */
    public function isDraft(): bool
    {
        return $this->submitted_at === null;
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

    /** Finalized cases only - drafts are invisible everywhere except the intake screen that owns them. */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('submitted_at');
    }

    /**
     * Deletes this case along with every uploaded evidence file - both the
     * disk bytes (which `delete()` alone never touches) and the DB rows
     * (which cascade automatically via the case_documents FK). Used for an
     * explicit "discard draft" and for the abandoned-draft cleanup sweep.
     */
    public function purgeWithDocuments(): void
    {
        $disk = Storage::disk(config('casehub.case_documents.disk'));

        foreach ($this->documents as $document) {
            $disk->delete($document->path);
        }

        $this->delete();
    }
}
