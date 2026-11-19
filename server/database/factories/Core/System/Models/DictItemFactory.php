<?php

namespace Database\Factories\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Core\System\Models\{DictItem, DictType};
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictItem>
 */
class DictItemFactory extends Factory
{
    /**
     * Define the model's default state：自定义、启用的字典项。
     *
     * 列出全部字段（可空的也写上）：严格模式下访问未设置的属性会报错。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dict_type_id' => DictType::factory(),
            'value' => fake()->unique()->regexify('[a-z]{8}'),
            'label' => fake()->words(2, true),
            'color' => null,
            'sort' => 0,
            'status' => Status::Enabled,
            'is_builtin' => false,
        ];
    }

    /**
     * 内置字典项（模块声明的）。
     */
    public function builtin(): static
    {
        return $this->state(fn (array $attributes): array => ['is_builtin' => true]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => Status::Disabled]);
    }
}
