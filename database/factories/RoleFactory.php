<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'is_active' => true,
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'name' => 'Super Admin',
            'slug' => Role::SUPER_ADMIN_SLUG,
            'is_system' => true,
        ]);
    }

    /** @param  list<string>  $permissions */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(fn (Role $role) => $role->syncPermissions($permissions));
    }
}
