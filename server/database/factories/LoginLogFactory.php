<?php

namespace Database\Factories;

use App\Enums\LoginStatus;
use App\Models\LoginLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginLog>
 */
class LoginLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement([LoginStatus::Success, LoginStatus::Success, LoginStatus::Success, LoginStatus::Failed]);

        return [
            'user_id' => null,
            'username' => fake()->userName(),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'status' => $status,
            'message' => $status === LoginStatus::Success ? '登录成功。' : '用户名或密码错误。',
            'created_at' => fake()->dateTimeBetween('-13 days'),
        ];
    }
}
