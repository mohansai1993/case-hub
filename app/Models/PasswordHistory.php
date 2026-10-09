<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only - see the migration for why this exists. */
class PasswordHistory extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];
}
