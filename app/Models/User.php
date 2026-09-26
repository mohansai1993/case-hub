<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Support\Identifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * An app-side account: a Client or a Lawyer (see `type`). Admin-panel
 * accounts live in the separate `admins` table (App\Models\Admin).
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    // type/status/mobile_verified_at are deliberately NOT mass-assignable from
    // request data; services set them explicitly.
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_verified',
        'mobile',
        'image',
    ];

    protected $casts = [
        'role'               => 'integer',
        'is_verified'        => 'boolean',
        'email_verified_at'  => 'datetime',
        'mobile_verified_at' => 'datetime',
        'type'               => UserType::class,
        'status'             => UserStatus::class,
        'password'           => 'hashed',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function ($user) {
            if (empty($user->user_id)) {
                $user->user_id = (string) Str::uuid();
            }
        });
    }

    // -- Normalised attributes ---------------------------------------------

    protected function email(): Attribute
    {
        return Attribute::set(fn (string $value) => mb_strtolower(trim($value)));
    }

    protected function mobile(): Attribute
    {
        return Attribute::set(fn (?string $value) => filled($value)
            ? (Identifier::normalizeMobile($value) ?? $value)
            : null);
    }

    // -- Relations ------------------------------------------------------------

    public function lawyerProfile(): HasOne
    {
        return $this->hasOne(LawyerProfile::class, 'user_id', 'user_id');
    }

    public function practiceAreas(): BelongsToMany
    {
        return $this->belongsToMany(PracticeArea::class, 'lawyer_practice_area', 'user_id', 'practice_area_id');
    }

    public function accountActions(): HasMany
    {
        return $this->hasMany(AccountAction::class, 'user_id', 'user_id')->latest('id');
    }

    // -- Scopes / lookups ---------------------------------------------------------

    public function scopeClients(Builder $query): Builder
    {
        return $query->where('type', UserType::Client->value);
    }

    public function scopeLawyers(Builder $query): Builder
    {
        return $query->where('type', UserType::Lawyer->value);
    }

    /** Short readable reference for the admin panel, e.g. CL-1A2B3C4D. */
    public function reference(): string
    {
        return ($this->isLawyer() ? 'LW-' : 'CL-') . strtoupper(substr(str_replace('-', '', $this->user_id), 0, 8));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/^(adv|dr|mr|ms|mrs)\.?\s+/i', '', $this->name)));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('mobile_verified_at');
    }

    public static function findByIdentifier(Identifier $identifier): ?self
    {
        return static::where($identifier->column(), $identifier->value)->first();
    }

    // -- State ------------------------------------------------------------------

    public function isClient(): bool
    {
        return $this->type === UserType::Client;
    }

    public function isLawyer(): bool
    {
        return $this->type === UserType::Lawyer;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasVerifiedMobile(): bool
    {
        return $this->mobile_verified_at !== null;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    // -- Notifications ------------------------------------------------------------

    public function routeNotificationForSms(): ?string
    {
        return $this->mobile;
    }
}
