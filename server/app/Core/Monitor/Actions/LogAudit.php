<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Contracts\Auditable;
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Activitylog\Contracts\Activity;

/**
 * 数据变更审计（Core 的扩展点“日志”，设计文档 §5.5、§6.11）：写入 activitylog，各模块的审计共用这一份写法，模型只提供快照（Auditable）。
 *
 * 由模块的 Action 在**同一个事务**中调用：写入失败与业务写入一起回滚，审计与数据一致。不用 activitylog 的 LogsActivity trait：
 * 它只有模型事件（created / updated / deleted / restored），分不出发布、取消发布与彻底删除，也无法改写大字段。
 *
 * - 日志名 = 模型所属模块的标识（设计文档 §4.4）：模块目录名的 snake_case——Core 为 `system`、`monitor`、`message`，
 *   业务模块为模块名（如 `content`）。模块按多态别名登记的模型类的命名空间（`App\Core\<模块>`、`App\Modules\<模块>`）确定；
 *   `operation`、`login` 是 Monitor 保留给操作日志、登录日志的日志名，不是模块标识；
 * - subject 为记录，causer 为操作者；event 为事件名（snake_case 过去分词，如 created、published、force_deleted）；
 *   description 为 `<日志名>.<模型标识>.<event>`，模型标识是多态别名去掉模块前缀（如 `content.article.published`、
 *   `system.user.disabled`：稳定的标识，由前端翻译，后端不存翻译后的文案）；
 *   properties.username 是操作者的快照，properties.request_id 由 Activity 模型自动写入；
 * - 字段变化用 spatie 的 `attributes` / `old` 结构：
 *   - `created`：attributes 为新增后的快照；
 *   - `deleted`、`restored`：只记事件；
 *   - `force_deleted`：old 为删除前的快照（$before）；
 *   - 其他事件（updated、published、unpublished……）：只记变化了的字段（old / attributes），没有变化时不写，返回 null；
 * - 大字段（Auditable::auditDigestedFields()）不存原文，记为 `{ sha256, length }`。
 */
class LogAudit
{
    /**
     * 只记事件、不记字段的事件。
     *
     * @var list<string>
     */
    private const array EVENT_ONLY = ['deleted', 'restored'];

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>|null  $before  操作前的 auditSnapshot()：修改类事件与 force_deleted 时提供
     */
    public function handle(User $actor, Model&Auditable $subject, string $event, ?array $before = null): ?Activity
    {
        $properties = match (true) {
            $event === 'created' => ['attributes' => $this->snapshot($subject, $subject->auditSnapshot())],
            in_array($event, self::EVENT_ONLY, true) => [],
            $event === 'force_deleted' => ['old' => $this->snapshot($subject, $before ?? [])],
            default => $this->changes($this->snapshot($subject, $before ?? []), $this->snapshot($subject, $subject->auditSnapshot())),
        };

        if ($properties === [] && ! in_array($event, self::EVENT_ONLY, true)) {
            return null;
        }

        $alias = $subject->getMorphClass();
        $logName = $this->moduleOf($alias, $subject);

        return activity($logName)
            ->performedOn($subject)
            ->causedBy($actor)
            ->event($event)
            ->withProperties(['username' => $actor->username, ...$properties])
            ->log("{$logName}.".Str::after($alias, '.').".{$event}");
    }

    /**
     * 模型所属模块的标识：多态别名登记的模型类（没有登记时为 subject 的类）所在的 `App\Core\<模块>` / `App\Modules\<模块>`，转为 snake_case。
     *
     * @throws LogicException 模型不在 Core 或业务模块中
     */
    private function moduleOf(string $alias, Model $subject): string
    {
        $class = Relation::getMorphedModel($alias) ?? $subject::class;

        if (preg_match('/^App\\\\(?:Core|Modules)\\\\(?<module>[^\\\\]+)\\\\/', $class, $match) !== 1) {
            throw new LogicException("无法确定 [{$class}] 所属的模块：审计的模型须在 App\\Core\\<模块> 或 App\\Modules\\<模块> 中。");
        }

        return Str::snake($match['module']);
    }

    /**
     * 写入日志的快照：大字段换成摘要。
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function snapshot(Auditable $subject, array $snapshot): array
    {
        foreach ($subject->auditDigestedFields() as $field) {
            $value = $snapshot[$field] ?? null;

            if (is_string($value)) {
                $snapshot[$field] = ['sha256' => hash('sha256', $value), 'length' => mb_strlen($value)];
            }
        }

        return $snapshot;
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
