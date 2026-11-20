<?php

namespace App\Core\Monitor\Queries;

use App\Core\Monitor\Models\LoginLog;
use App\Core\System\Models\User;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 登录日志的列表查询（GET /monitor/login-logs，设计文档 §6.6）：经过数据范围 visibleTo($actor)（委托给日志的用户，§8.4）。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class LoginLogList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<LoginLog>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(LoginLog::query()->visibleTo($actor), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::exact('user_id', 'causer_id'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('reason', 'properties->reason'),
                AllowedFilter::exact('request_id', 'properties->request_id'),
                AllowedFilter::partial('username', 'properties->username'),
                AllowedFilter::partial('ip_address', 'properties->ip_address'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id', 'created_at')
            ->defaultSort('-id');
    }
}
