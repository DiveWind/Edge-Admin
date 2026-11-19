<?php

namespace Database\Factories\Core\Monitor\Models;

use App\Core\Monitor\Models\Session;
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 会话表中的一行（与框架的 DatabaseSessionHandler 写入的格式相同）：默认是某个用户刚刚活动过的在线会话。
 *
 * @extends Factory<Session>
 */
class SessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::random(40),
            'user_id' => User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ];
    }

    /**
     * 未登录的会话（user_id 为空）。
     */
    public function guest(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }

    /**
     * 已过期的会话：最后活动早于 session.lifetime 分钟。
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'last_activity' => now()->subMinutes(config()->integer('session.lifetime') + 1)->getTimestamp(),
        ]);
    }
}
