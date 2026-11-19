<?php

namespace App\Core\Monitor\Models;

use App\Core\System\Models\User;
use App\Core\System\Services\DataScopeRegistry;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use NoDiscard;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * activitylog 的日志记录（多态别名 `activity`，见 MonitorServiceProvider；config('activitylog.activity_model') 指向本类，设计文档 §6.6）。
 *
 * 操作日志、登录日志与各模块的数据变更审计统一存在 activity_log 表中，按日志名（log_name）区分：
 * `operation`（OperationLog）、`login`（LoginLog）是本模块保留的日志名，模块的审计使用模块自己的日志名（§5.5）。
 *
 * - 写入时自动在 properties.request_id 中记下当前请求 ID（Context 的 `request_id`，由 AssignRequestId 中间件写入，并随队列任务传递），
 *   同一请求产生的操作日志与模块审计可以互相关联；
 * - 数据范围委托给 causer（§8.4）：visibleTo() / isVisibleTo() 与关联用户的 User::visibleTo() 同一规则，
 *   不受数据范围限制的操作者（DataScopeRegistry::isUnrestrictedFor()）另可看到没有 causer 的日志。
 *
 * 写入只通过 activity() 助手（在本模块的 Action 中，或模块模型的 LogsActivity），不直接 create()。
 */
class Activity extends SpatieActivity
{
    use UsesAppTimezone;

    /**
     * Context 中请求 ID 的键（与 App\Http\Middleware\AssignRequestId::CONTEXT_KEY 相同；Core 不依赖入口，这里单独声明）。
     */
    public const string REQUEST_ID = 'request_id';

    /**
     * 写入前补上请求 ID（已有时不覆盖；命令行等没有请求 ID 时不写）。
     */
    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $requestId = Context::get(self::REQUEST_ID);
            $properties = $activity->properties ?? new Collection;

            if (is_string($requestId) && ! $properties->has(self::REQUEST_ID)) {
                $activity->properties = $properties->put(self::REQUEST_ID, $requestId);
            }
        });
    }

    /**
     * 只保留 $actor 可见的日志：causer 是 $actor 可见的用户；不受数据范围限制时不加条件（含没有 causer 的日志）。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $actor): void
    {
        if (resolve(DataScopeRegistry::class)->isUnrestrictedFor($actor)) {
            return;
        }

        $query->whereHasMorph('causer', [User::class], fn (Builder $users) => $users->visibleTo($actor));
    }

    /**
     * 本条日志是否对 $actor 可见（与 visibleTo() 同一规则）。
     */
    #[NoDiscard]
    public function isVisibleTo(User $actor): bool
    {
        if (resolve(DataScopeRegistry::class)->isUnrestrictedFor($actor)) {
            return true;
        }

        $causer = $this->causer;

        return $causer instanceof User && $causer->isVisibleTo($actor);
    }

    /**
     * properties 中的字符串值（不存在或不是字符串时为 null）。
     */
    public function stringProperty(string $key): ?string
    {
        $value = $this->getProperty($key);

        return is_string($value) ? $value : null;
    }
}
