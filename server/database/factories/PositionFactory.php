<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->jobTitle(),
            'code' => fake()->unique()->slug(2),
            'sort' => fake()->numberBetween(0, 100),
            'status' => Status::Active,
            'remark' => null,
        ];
    }
}
