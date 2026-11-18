<?php

namespace Database\Factories\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

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
     * 列出全部字段（可空的也写上）：actingAs() 把用户当作从数据库读出的，严格模式下访问未设置的属性会报错。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => Str::lower(fake()->unique()->bothify('user_####??')),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'avatar' => null,
            'status' => Status::Enabled,
            'created_by' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * 已开启并确认两步验证（Fortify）：密钥与 8 个恢复码加密保存。
     */
    public function withTwoFactorAuthentication(): static
    {
        return $this->state(fn (array $attributes): array => [
            'two_factor_secret' => encrypt(resolve(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
            'two_factor_recovery_codes' => encrypt((string) json_encode(array_map(RecoveryCode::generate(...), range(1, 8)))),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * 停用的账号。
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Status::Disabled,
        ]);
    }
}
