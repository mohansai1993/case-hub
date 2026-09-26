<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

class Role extends Model
{
    use HasFactory;

    public const SUPER_ADMIN_SLUG = 'super-admin';

    protected $fillable = ['name', 'slug', 'tag', 'description', 'is_active'];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** @var list<string>|null */
    private ?array $permissionCache = null;

    protected static function booted(): void
    {
        static::deleting(function (Role $role) {
            if ($role->is_system) {
                throw new LogicException('System roles cannot be deleted.');
            }
        });

        static::saving(function (Role $role) {
            // A system role must stay switched on, otherwise the Super Admin
            // would lock everyone (including themselves) out.
            if ($role->is_system && ! $role->is_active) {
                throw new LogicException('System roles cannot be deactivated.');
            }
        });
    }

    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN_SLUG;
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        return $this->permissionCache ??= DB::table('role_permissions')
            ->where('role_id', $this->getKey())
            ->pluck('permission')
            ->all();
    }

    /**
     * Replaces the role's permissions. Unknown keys are discarded.
     *
     * @param  iterable<string>  $keys
     */
    public function syncPermissions(iterable $keys): void
    {
        if ($this->isSuperAdmin()) {
            throw new LogicException('The Super Admin role implicitly has every permission.');
        }

        $keys = Permissions::only($keys);

        DB::transaction(function () use ($keys) {
            DB::table('role_permissions')->where('role_id', $this->getKey())->delete();

            if ($keys) {
                DB::table('role_permissions')->insert(array_map(
                    fn (string $key) => ['role_id' => $this->getKey(), 'permission' => $key],
                    $keys,
                ));
            }
        });

        $this->permissionCache = $keys;
    }
}
