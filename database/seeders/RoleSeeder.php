<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Ensures the built-in Super Admin role exists. Safe to run repeatedly.
     * Other roles are created from the admin panel.
     */
    public function run(): void
    {
        Role::unguarded(fn () => Role::updateOrCreate(
            ['slug' => Role::SUPER_ADMIN_SLUG],
            [
                'name' => 'Super Admin',
                'tag' => 'System Default',
                'description' => 'Full access to all admin features',
                'is_system' => true,
                'is_active' => true,
            ],
        ));
    }
}
