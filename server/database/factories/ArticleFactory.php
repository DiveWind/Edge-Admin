<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * 标题素材：主题 × 角度随机组合。
     *
     * @var list<string>
     */
    protected const array TOPICS = [
        'Laravel 队列', 'Eloquent 关系', 'RBAC 权限模型', '数据权限', '前后端分离', 'API 设计', '接口限流', '定时任务',
        '日志审计', '缓存策略', '数据库索引', '代码评审', '自动化测试', '持续集成', '产品需求评审', '用户增长',
        '品牌营销', '季度复盘', '团队协作', '技术选型',
    ];

    /**
     * @var list<string>
     */
    protected const array ANGLES = [
        '实践指南', '入门与进阶', '常见问题汇总', '落地经验分享', '最佳实践', '踩坑记录', '设计思路', '一次完整复盘',
    ];

    /**
     * 正文段落素材。
     *
     * @var list<string>
     */
    protected const array PARAGRAPHS = [
        '在中后台系统中，权限往往是最先被设计、也最容易被反复修改的部分。把权限点声明在代码里，再同步到数据库，可以让每一次变更都有迹可循。',
        '数据权限解决的是"能看到哪些数据"的问题，功能权限解决的是"能做哪些操作"的问题，二者需要分开设计、组合使用。',
        '我们在项目初期就约定了统一的响应格式和错误码，前后端联调时几乎没有出现字段对不上的情况，沟通成本大幅下降。',
        '队列适合处理耗时且允许异步完成的任务，例如批量发送通知、生成报表和同步第三方数据。关键是让任务具备幂等性。',
        '定时任务需要可观测：每次执行的开始时间、耗时、输出和失败原因都应该被记录下来，出现问题时才能快速定位。',
        '操作日志不仅用于审计，也是排查线上问题的重要线索。记录请求时要注意对密码、令牌等敏感字段进行脱敏。',
        '良好的测试覆盖让重构变得安全。我们优先为权限、数据范围等关键路径编写功能测试，再逐步补充边界场景。',
        '在需求评审阶段明确验收标准，可以显著减少返工。每个需求都应说明使用场景、权限要求和异常情况的处理方式。',
        '本季度团队完成了核心模块的重构，接口平均响应时间下降了约三成，同时线上告警数量明显减少。',
        '市场活动结束后，我们对各渠道的转化数据进行了复盘，发现内容质量对用户留存的影响远大于投放预算。',
        '技术选型时既要考虑社区活跃度和长期维护成本，也要考虑团队现有的知识储备，避免为了新而新。',
        '代码评审的目标不是挑错，而是让知识在团队中流动。评审意见应当具体、可执行，并说明背后的原因。',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(PublishStatus::cases());
        $paragraphs = fake()->randomElements(self::PARAGRAPHS, fake()->numberBetween(3, 5));

        return [
            'category_id' => ArticleCategory::factory(),
            'title' => fake()->randomElement(self::TOPICS).fake()->randomElement(self::ANGLES),
            'summary' => $paragraphs[0],
            'cover' => null,
            'content' => implode("\n\n", $paragraphs),
            'status' => $status,
            'source' => fake()->randomElement(['original', 'original', 'reprint', 'translation']),
            'published_at' => $status === PublishStatus::Published ? fake()->dateTimeBetween('-30 days') : null,
            'department_id' => null,
            'created_by' => null,
        ];
    }

    /**
     * 指定作者，并以作者部门作为文章部门。
     */
    public function by(User $author): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_by' => $author->id,
            'department_id' => $author->department_id,
        ]);
    }
}
