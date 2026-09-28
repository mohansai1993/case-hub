<?php

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationBroadcast extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'audience' => UserType::class,
            'is_bulk' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(NotificationDraft::class, 'notification_draft_id');
    }

    /** "All Clients" for a bulk send, otherwise the snapshotted recipient names. */
    public function recipientsLabel(): string
    {
        if ($this->is_bulk) {
            return 'All ' . ($this->audience === UserType::Lawyer ? 'Lawyers' : 'Clients');
        }

        return $this->recipient_names ?: '—';
    }

    public function audienceLabel(): string
    {
        return $this->audience === UserType::Lawyer ? 'Lawyers' : 'Clients';
    }
}
