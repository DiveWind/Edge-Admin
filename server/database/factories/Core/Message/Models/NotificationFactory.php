<?php

namespace Database\Factories\Core\Message\Models;

use App\Core\Message\Models\Notification;
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 通知：默认是发给一个新用户的未读通知（类型 `demo.example`，结构同 NotificationContentData::toDatabase()）。
 * 真实的通知由 database 通道写入（$user->notify()），工厂只用于构造列表、清理等测试数据。
 *
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'demo.example',
            'notifiable_type' => 'user',
            'notifiable_id' => User::factory(),
            'data' => [
                'title' => fake()->sentence(3),
                'content' => fake()->sentence(),
                'level' => 'info',
                'link' => null,
                'params' => [],
                'subject' => null,
            ],
            'read_at' => null,
        ];
    }

    /**
     * 发给 $user。
     */
    public function ownedBy(User $user): static
    {
        return $this->state(fn (): array => ['notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id]);
    }

    /**
     * 已读。
     */
    public function read(): static
    {
        return $this->state(fn (): array => ['read_at' => now()]);
    }
}
