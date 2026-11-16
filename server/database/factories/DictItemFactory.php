<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\DictItem;
use App\Models\DictType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictItem>
 */
class DictItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dict_type_id' => DictType::factory(),
            'label' => fake()->word(),
            'value' => fake()->unique()->lexify('????????'),
            'color' => null,
            'sort' => fake()->numberBetween(0, 100),
            'status' => Status::Active,
            'remark' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => Status::Disabled]);
    }
}
