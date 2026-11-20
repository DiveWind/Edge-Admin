<?php

namespace App\Core\Monitor\Queries;

use App\Core\Monitor\Models\OperationLog;
use App\Core\System\Models\User;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 操作日志的列表查询（GET /monitor/operation-logs，设计文档 §6.6）：经过数据范围 visibleTo($actor)（委托给日志的操作者，§8.4）；status 按整数比较。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class OperationLogList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<OperationLog>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(OperationLog::query()->visibleTo($actor), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::exact('user_id', 'causer_id'),
                AllowedFilter::exact('method', 'properties->method'),
                AllowedFilter::callback('status', fn (Builder $query, mixed $value) => $query->where('properties->status', is_numeric($value) ? (int) $value : 0)),
                AllowedFilter::exact('request_id', 'properties->request_id'),
                AllowedFilter::partial('username', 'properties->username'),
                AllowedFilter::partial('description'),
                AllowedFilter::partial('ip_address', 'properties->ip_address'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id', 'created_at')
            ->defaultSort('-id');
    }
}
