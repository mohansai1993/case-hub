<?php

namespace Database\Factories;

use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Admin> */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public const PASSWORD = 'Password@123';

    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'mobile' => '9' . fake()->unique()->numerify('#########'),
            'password' => self::PASSWORD,
            'status' => AdminStatus::Active,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role_id' => Role::where('slug', Role::SUPER_ADMIN_SLUG)->first()?->id
                ?? Role::factory()->superAdmin(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => AdminStatus::Inactive]);
    }

    /** @param  list<string>  $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->state(fn () => ['role_id' => Role::factory()->withPermissions($permissions)]);
    }
}
