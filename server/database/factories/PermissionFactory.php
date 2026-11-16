<?php

namespace Database\Factories;

use App\Enums\PermissionType;
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
            'code' => fake()->unique()->slug(3),
            'type' => PermissionType::Action,
            'sort' => 0,
        ];
    }
}
