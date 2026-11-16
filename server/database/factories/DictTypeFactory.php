<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\DictType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictType>
 */
class DictTypeFactory extends Factory
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
            'code' => fake()->unique()->lexify('dict_????????'),
            'status' => Status::Active,
            'remark' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => Status::Disabled]);
    }
}
