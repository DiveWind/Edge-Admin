<?php

namespace App\Core\Monitor\Queries;

use App\Core\Monitor\Models\{ScheduledTask, ScheduledTaskLog};
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 某个定时任务的执行记录（GET /monitor/schedules/{schedule}/logs，设计文档 §6.6）：嵌套列表，以上级记录代替操作者（只看权限码）。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class ScheduledTaskLogList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<ScheduledTaskLog>
     */
    public function builder(ScheduledTask $task, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(ScheduledTaskLog::query()->where('monitored_scheduled_task_id', $task->id), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::exact('type'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id')
            ->defaultSort('-id');
    }
}
