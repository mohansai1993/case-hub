<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Creates the first Super Admin. Idempotent: if that email already exists
     * (or any Super Admin does) nothing is created or changed, so re-running
     * db:seed never duplicates the account or resets its password.
     */
    public function run(): void
    {
        $config = config('casehub.seed_admin');
        $email = mb_strtolower($config['email']);

        if (Admin::where('email', $email)->exists()) {
            $this->command?->info("Admin {$email} already exists - skipped.");

            return;
        }

        $superRole = Role::where('slug', Role::SUPER_ADMIN_SLUG)->first();

        if ($superRole && $superRole->admins()->exists()) {
            $this->command?->info('A Super Admin already exists - skipped.');

            return;
        }

        $password = $config['password'] ?: (app()->isLocal() ? 'Password@123' : null);

        if (! $password) {
            $this->command?->warn('ADMIN_SEED_PASSWORD is not set - Super Admin not created. Set it or run: php artisan admin:create-super');

            return;
        }

        Admin::create([
            'role_id' => $superRole->id,
            'name' => $config['name'],
            'email' => $email,
            'mobile' => $config['mobile'] ?: null,
            'password' => $password,
            'status' => 'active',
        ]);

        $this->command?->info("Super Admin created: {$email}");
    }
}
