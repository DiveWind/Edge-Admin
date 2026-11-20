<?php

namespace App\Core\Monitor\Queries;

use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 在线会话页的 token 列表（GET /monitor/online/tokens，设计文档 §6.6）：$actor 可见的、仍然有效的后台用户 token。
 *
 * - 有效：expires_at 为空或晚于现在，且配置了 sanctum.expiration（分钟）时创建于该时间之内——与 Sanctum 的 Guard 校验一致；
 * - 只含属于后台用户（多态别名 `user`）的 token，数据范围委托给所属用户（User::visibleTo()，§8.4）；
 * - 预加载 tokenable（所属用户），输出时不再查库。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class OnlineTokenList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<PersonalAccessToken>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        $expiration = config('sanctum.expiration');
        $minutes = is_numeric($expiration) ? (int) $expiration : null;

        $tokens = PersonalAccessToken::query()
            ->whereHasMorph('tokenable', [User::class], fn (Builder $users) => $users->visibleTo($actor))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($minutes !== null, fn (Builder $query) => $query->where('created_at', '>', now()->subMinutes((int) $minutes)))
            ->with('tokenable');

        return QueryBuilder::for($tokens, new Request($parameters))
            ->allowedFilters(
                AllowedFilter::exact('user_id', 'tokenable_id'),
                AllowedFilter::callback('username', fn (Builder $query, mixed $value) => $query->whereHasMorph(
                    'tokenable',
                    [User::class],
                    fn (Builder $users) => $users->whereLike('username', '%'.(is_scalar($value) ? $value : '').'%'),
                )),
                AllowedFilter::partial('name'),
            )
            ->allowedSorts('id', 'last_used_at', 'created_at')
            ->defaultSort('-id');
    }
}
