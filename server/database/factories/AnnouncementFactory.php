<?php

namespace Database\Factories;

use App\Enums\{AnnouncementType, PublishStatus};
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    protected const array SAMPLES = [
        ['系统升级通知', '系统将于本周六凌晨进行版本升级，届时可能短暂无法访问，请提前保存工作内容。'],
        ['关于启用新版权限管理的通知', '新版权限管理已上线，各部门管理员请检查本部门成员的角色与数据权限配置。'],
        ['年度安全培训安排', '年度信息安全培训将于下月初开展，请全体成员按时参加并完成线上考试。'],
        ['办公网络维护公告', '因网络设备维护，周三 18:00 - 20:00 办公网络将间歇性中断。'],
        ['密码安全提醒', '为保障账号安全，请定期修改登录密码，且不要在多个系统中使用相同密码。'],
        ['新功能上线：站内通知', '现在可以在右上角查看站内通知，重要公告会第一时间推送给你。'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$title, $content] = fake()->randomElement(self::SAMPLES);

        return [
            'title' => $title,
            'content' => $content,
            'type' => fake()->randomElement(AnnouncementType::cases()),
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ]);
    }
}
