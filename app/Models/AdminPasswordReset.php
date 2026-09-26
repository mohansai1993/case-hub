<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPasswordReset extends Model
{
    protected $guarded = [];

    protected $hidden = ['otp_hash', 'reset_token_hash'];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'verified_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
