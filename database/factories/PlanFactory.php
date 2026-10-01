<?php

namespace Database\Factories;

use App\Enums\PlanStorageUnit;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'storage_amount' => fake()->randomElement([500, 1, 5, 10]),
            'storage_unit' => fake()->randomElement(PlanStorageUnit::cases()),
            'price' => fake()->numberBetween(99, 999),
            'description' => fake()->sentence(),
            'is_popular' => false,
            'is_active' => true,
        ];
    }

    public function popular(): static
    {
        return $this->state(fn () => ['is_popular' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
