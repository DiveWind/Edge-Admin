<?php

namespace App\Modules\Content\Actions;

use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use Spatie\Activitylog\Contracts\Activity;

/**
 * 文章的审计日志（设计文档 §5.5、§6.11）：写入 activitylog，日志名 `content`（模块名小写；`operation`、`login` 是 Monitor 保留的）。
 *
 * 由本模块的其他 Action 在**同一个事务**中调用：写入失败与业务写入一起回滚，审计与数据一致。不用 activitylog 的 LogsActivity trait：
 * 它只有模型事件（created / updated / deleted / restored），分不出发布、取消发布与彻底删除，也无法改写大字段。
 *
 * - subject 为文章（多态别名 content.article），causer 为操作者；event 为 EVENTS 之一，description 为 `content.article.<event>`
 *   （稳定的标识，由前端翻译，后端不存翻译后的文案）；properties.username 是操作者的快照，properties.request_id 由 Monitor 的 Activity 自动写入；
 * - 字段变化用 spatie 的 `attributes` / `old` 结构，字段见 snapshot()：新增记 attributes；修改、发布、取消发布只记变化了的字段（没有变化时不写）；
 *   彻底删除记 old（删除前的全部字段）；删除、恢复只记事件；
 * - 大字段 content 不存原文，记为 `{ sha256, length }`（能看出是否变化，并可与某个版本核对）。
 */
class LogArticleActivity
{
    /**
     * 日志名。
     */
    public const string LOG_NAME = 'content';

    public const string CREATED = 'created';

    public const string UPDATED = 'updated';

    public const string PUBLISHED = 'published';

    public const string UNPUBLISHED = 'unpublished';

    public const string DELETED = 'deleted';

    public const string RESTORED = 'restored';

    public const string FORCE_DELETED = 'force_deleted';

    /**
     * 记录字段变化（old / attributes）的事件。
     *
     * @var list<string>
     */
    private const array CHANGE_EVENTS = [self::UPDATED, self::PUBLISHED, self::UNPUBLISHED];

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>|null  $before  操作前的 snapshot()：修改、发布、取消发布、彻底删除时必须提供
     */
    public function handle(User $actor, Article $article, string $event, ?array $before = null): ?Activity
    {
        $properties = match (true) {
            $event === self::CREATED => ['attributes' => $this->snapshot($article)],
            in_array($event, self::CHANGE_EVENTS, true) => $this->changes($before ?? [], $this->snapshot($article)),
            $event === self::FORCE_DELETED => ['old' => $before ?? []],
            default => [],
        };

        if (in_array($event, self::CHANGE_EVENTS, true) && $properties === []) {
            return null;
        }

        return activity(self::LOG_NAME)
            ->performedOn($article)
            ->causedBy($actor)
            ->event($event)
            ->withProperties(['username' => $actor->username, ...$properties])
            ->log("content.article.{$event}");
    }

    /**
     * 审计的字段（已保存的值）：title、summary、category、status、published_at（ISO 8601）、cover_id（封面附件的 id）、
     * content（`{ sha256, length }`：净化后 HTML 的摘要与字符数）。
     *
     * @return array{title: string, summary: string|null, category: string, status: string, published_at: string|null, cover_id: int|null, content: array{sha256: string, length: int}}
     */
    public function snapshot(Article $article): array
    {
        $coverId = $article->cover()->value('id');

        return [
            'title' => $article->title,
            'summary' => $article->summary,
            'category' => $article->category,
            'status' => $article->status->value,
            'published_at' => $article->published_at?->toIso8601String(),
            'cover_id' => is_numeric($coverId) ? (int) $coverId : null,
            'content' => ['sha256' => hash('sha256', $article->content), 'length' => mb_strlen($article->content)],
        ];
    }

    /**
     * 变化了的字段：`{ old, attributes }`；没有变化时为空数组。
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{old?: array<string, mixed>, attributes?: array<string, mixed>}
     */
    private function changes(array $before, array $after): array
    {
        $changed = array_keys(array_filter($after, fn (mixed $value, string $key): bool => ($before[$key] ?? null) !== $value, ARRAY_FILTER_USE_BOTH));

        if ($changed === []) {
            return [];
        }

        $keys = array_flip($changed);

        return ['old' => array_intersect_key($before, $keys), 'attributes' => array_intersect_key($after, $keys)];
    }
}
