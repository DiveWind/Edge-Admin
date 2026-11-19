<?php

namespace Database\Factories\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Core\System\Models\DictType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DictType>
 */
class DictTypeFactory extends Factory
{
    /**
     * Define the model's default state：自定义、启用的类型。
     *
     * 列出全部字段（可空的也写上）：严格模式下访问未设置的属性会报错。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->regexify('[a-z]{6}_[a-z]{4}'),
            'name' => fake()->words(2, true),
            'status' => Status::Enabled,
            'remark' => null,
            'is_builtin' => false,
        ];
    }

    /**
     * 内置类型（模块声明的）。
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
