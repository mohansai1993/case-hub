<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAction extends Model
{
    public const SUSPENDED = 'suspended';
    public const ACTIVATED = 'activated';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function label(): string
    {
        return match ($this->action) {
            self::SUSPENDED => 'Account suspended',
            self::ACTIVATED => 'Account activated',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
