<?php

namespace App\Core\Message\Queries;

use App\Core\Message\Models\Notification;
use App\Core\System\Models\User;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 我的通知（GET /notifications，设计文档 §6.9）：只含 $actor 自己的通知（ownedBy()）。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class NotificationList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<Notification>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(Notification::query()->ownedBy($actor), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::callback('is_read', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereNotNull('read_at')
                    : $query->whereNull('read_at')),
                AllowedFilter::exact('type'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('created_at', 'read_at')
            ->defaultSort('-created_at');
    }
}
