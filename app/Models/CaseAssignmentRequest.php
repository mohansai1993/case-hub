<?php

namespace App\Models;

use App\Enums\CaseAssignmentRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAssignmentRequest extends Model
{
    protected $table = 'case_assignment_requests';

    // Only ever created/updated by CaseAssignmentService / CaseAssignmentModerationService with an explicit field list.
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => CaseAssignmentRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lawyer_id', 'user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by', 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CaseAssignmentRequestStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === CaseAssignmentRequestStatus::Pending;
    }
}
