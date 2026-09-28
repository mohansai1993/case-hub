<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PracticeArea extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function lawyers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lawyer_practice_area', 'practice_area_id', 'user_id');
    }
}
