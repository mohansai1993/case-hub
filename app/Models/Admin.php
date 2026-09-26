<?php

namespace App\Models;

use App\Enums\AdminStatus;
use App\Support\Identifier;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An admin-panel account: the Super Admin or a staff member whose access is
 * defined by their role.
 *
 * @property string $id
 * @property AdminStatus $status
 */
class Admin extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = ['role_id', 'name', 'email', 'mobile', 'password', 'status'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => AdminStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /** @var list<string>|null */
    private ?array $permissionCache = null;

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

    // -- Relations / lookups ------------------------------------------------

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function findByIdentifier(Identifier $identifier): ?self
    {
        return static::where($identifier->column(), $identifier->value)->first();
    }

    // -- State --------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === AdminStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    // -- Authorization ------------------------------------------------------

    /**
     * Named hasPermission() rather than can(): can() belongs to Laravel's
     * Gate/Authorizable and must keep its own meaning.
     *
     * Inactive admins and admins whose role is switched off hold nothing.
     */
    public function hasPermission(string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionKeys(), true);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        if ($this->role === null || ! $this->role->is_active) {
            return [];
        }

        return $this->permissionCache ??= $this->role->permissionKeys();
    }

    /** Route name of the first section this admin may open, or null if none. */
    public function homeRoute(): ?string
    {
        foreach (config('permissions.landing', []) as $permission => $route) {
            if ($this->hasPermission($permission)) {
                return $route;
            }
        }

        return null;
    }

    // -- Presentation -------------------------------------------------------

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim(preg_replace('/^(adv|dr|mr|ms|mrs)\.?\s+/i', '', $this->name)));

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
    }

    // -- Notifications ------------------------------------------------------

    public function routeNotificationForSms(): ?string
    {
        return $this->mobile;
    }
}
