<?php

namespace App\Core\Monitor\Queries;

use App\Core\Monitor\Models\Session;
use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 在线会话的列表查询（GET /monitor/online/sessions，设计文档 §6.6）：只含未过期的会话（online()），经过数据范围 visibleTo($actor)（委托给会话的用户，§8.4），预加载 user。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class OnlineSessionList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<Session>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(Session::query()->online()->visibleTo($actor)->with('user'), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::exact('user_id'),
                AllowedFilter::callback('username', fn (Builder $query, mixed $value) => $query->whereHas(
                    'user',
                    fn (Builder $users) => $users->whereLike('username', '%'.(is_scalar($value) ? $value : '').'%'),
                )),
                AllowedFilter::partial('ip_address'),
            )
            ->allowedSorts('last_activity')
            ->defaultSort('-last_activity');
    }
}
