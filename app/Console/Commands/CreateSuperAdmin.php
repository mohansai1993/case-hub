<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Role;
use App\Support\Identifier;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super
        {--name= : Full name}
        {--email= : Email address}
        {--mobile= : 10 digit mobile number (optional)}';

    protected $description = 'Create a Super Admin account (the way to bootstrap the first admin).';

    public function handle(): int
    {
        $this->callSilent('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);

        $name = $this->option('name') ?: text('Full name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $mobile = $this->option('mobile') ?: text('Mobile (10 digits, optional)');

        // The password is only ever prompted for, never passed as an option
        // (options end up in shell history and process lists).
        $password = password('Password', required: true);

        $data = compact('name', 'email', 'mobile', 'password');
        $data['mobile'] = $mobile !== '' ? (Identifier::normalizeMobile($mobile) ?? $mobile) : null;

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'mobile' => ['nullable', 'regex:/^[6-9]\d{9}$/', 'unique:admins,mobile'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Admin::create([
            'role_id' => Role::where('slug', Role::SUPER_ADMIN_SLUG)->value('id'),
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'password' => $data['password'],
            'status' => 'active',
        ]);

        $this->info("Super Admin {$data['email']} created.");

        return self::SUCCESS;
    }
}
