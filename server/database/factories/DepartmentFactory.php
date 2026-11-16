<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->company(),
            'sort' => fake()->numberBetween(0, 100),
            'status' => Status::Active,
        ];
    }

    public function childOf(Department $parent): static
    {
        return $this->state(fn (array $attributes): array => ['parent_id' => $parent->id]);
    }
}
