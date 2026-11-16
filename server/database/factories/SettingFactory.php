<?php

namespace Database\Factories;

use App\Enums\SettingType;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'custom',
            'key' => 'custom.'.fake()->unique()->lexify('????????'),
            'name' => fake()->words(2, true),
            'type' => SettingType::String,
            'value' => fake()->word(),
            'is_public' => false,
            'is_system' => false,
            'remark' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => ['is_public' => true]);
    }

    /**
     * 内置参数（不可删除、不可改 key / type）。
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes): array => ['is_system' => true]);
    }

    public function ofType(SettingType $type, mixed $value): static
    {
        return $this->state(fn (array $attributes): array => ['type' => $type, 'value' => $value]);
    }
}
