<?php

namespace Database\Factories\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Core\System\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * 列出全部字段（可空的也写上）：严格模式下访问未设置的属性会报错。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::lower(fake()->unique()->bothify('role-####??')),
            'guard_name' => 'web',
            'title' => fake()->words(2, true),
            'status' => Status::Enabled,
            'data_scope' => 'self',
            'remark' => null,
            'sort' => 0,
        ];
    }

    /**
     * 超管角色（标识 super-admin）。
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes): array => ['name' => Role::SUPER_ADMIN, 'title' => 'Super Admin']);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => Status::Disabled]);
    }
}
