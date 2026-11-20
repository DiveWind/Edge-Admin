<?php

namespace App\Core\Monitor\Queries;

use App\Core\Monitor\Models\ScheduledTask;
use App\Core\System\Models\User;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 定时任务的列表查询（GET /monitor/schedules，设计文档 §6.6）：不受数据范围限制（系统数据，只看权限码）。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class ScheduledTaskList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<ScheduledTask>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(ScheduledTask::query(), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts('id', 'name', 'last_started_at')
            ->defaultSort('name');
    }
}
