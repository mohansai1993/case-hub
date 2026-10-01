<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A client's current plan. One row per client (unique client_id) - there is
 * never more than one active plan/top-up at a time; upgrading or downgrading
 * updates this same row instead of creating a new one.
 */
class ClientSubscription extends Model
{
    protected $fillable = [
        'client_id', 'plan_id', 'status', 'gateway_customer_id', 'gateway_subscription_id',
        'current_period_ends_at', 'cancelled_at', 'grace_ends_at', 'restricted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'restricted_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id', 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(SubscriptionCharge::class);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    /** Cancelled but still inside the 7-day grace window. */
    public function inGrace(): bool
    {
        return $this->status === SubscriptionStatus::Cancelled && $this->grace_ends_at?->isFuture();
    }

    public function isRestricted(): bool
    {
        return $this->status === SubscriptionStatus::Restricted;
    }

    /** Can still read existing cases/documents (paying now, or cancelled-but-in-grace). */
    public function hasReadAccess(): bool
    {
        return $this->isActive() || $this->inGrace();
    }
}
