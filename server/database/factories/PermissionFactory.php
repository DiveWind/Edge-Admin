<?php

namespace Database\Factories;

use App\Enums\PermissionType;
use App\Enums\Status;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
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
            'name' => fake()->word(),
            'code' => fake()->unique()->slug(2).'.'.fake()->unique()->word(),
            'type' => PermissionType::Action,
            'path' => null,
            'icon' => null,
            'is_external' => false,
            'is_visible' => true,
            'status' => Status::Active,
            'is_system' => false,
            'sort' => 0,
            'remark' => null,
        ];
    }

    public function directory(): static
    {
        return $this->state(fn (): array => [
            'type' => PermissionType::Directory,
            'path' => '/'.fake()->unique()->slug(1),
            'icon' => 'AppstoreOutlined',
        ]);
    }

    public function menu(): static
    {
        return $this->state(fn (): array => [
            'type' => PermissionType::Menu,
            'path' => '/'.fake()->unique()->slug(2),
            'icon' => 'TableOutlined',
        ]);
    }

    public function childOf(Permission $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => Status::Disabled]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['is_visible' => false]);
    }

    public function system(): static
    {
        return $this->state(fn (): array => ['is_system' => true]);
    }
}
