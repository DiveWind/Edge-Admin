<?php

namespace Database\Factories;

use App\Enums\DataScope;
use App\Enums\Status;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => fake()->unique()->slug(2),
            'data_scope' => DataScope::All,
            'sort' => fake()->numberBetween(0, 100),
            'status' => Status::Active,
            'remark' => null,
        ];
    }

    public function scope(DataScope $scope): static
    {
        return $this->state(fn (array $attributes): array => ['data_scope' => $scope]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => Status::Disabled]);
    }
}
