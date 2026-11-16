<?php

namespace Database\Factories;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => Str::slug(fake('en_US')->firstName(), '').fake()->unique()->numberBetween(10, 9999),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('1##########'),
            'avatar' => null,
            'department_id' => null,
            'last_login_at' => null,
            'last_login_ip' => null,
            'status' => Status::Active,
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    /**
     * 最近登录过（带 last_login_at / last_login_ip）。
     */
    public function recentlyLoggedIn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_login_at' => fake()->dateTimeBetween('-7 days'),
            'last_login_ip' => fake()->ipv4(),
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => Status::Disabled]);
    }

    /**
     * 附带超级管理员角色。
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::query()->where('code', Role::SUPER_ADMIN)->first()
                ?? Role::factory()->create(['name' => '超级管理员', 'code' => Role::SUPER_ADMIN]);

            $user->roles()->attach($role);
        });
    }
}
