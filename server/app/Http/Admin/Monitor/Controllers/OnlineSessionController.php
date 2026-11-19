<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\LogoutSession;
use App\Core\Monitor\Models\Session;
use App\Core\System\Models\User;
use App\Http\Admin\Monitor\Requests\OnlineSessionIndexRequest;
use App\Http\Admin\Monitor\Resources\OnlineSessionResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 在线会话（设计文档 §6.6、§7.3）：列表与强制下线。会话驱动不是 database 时列表为空、下线 404。
 *
 * 授权由 SessionPolicy 判定：查看 monitor.online，下线 monitor.online.logout；不能下线自己当前的会话、非超管不能下线超管（403）。
 */
class OnlineSessionController
{
    /**
     * 列表（在线、所属用户在数据范围内，预加载 user）：筛选 user_id（精确）、username、ip_address（模糊）；
     * 排序 last_activity，默认 `-last_activity`。
     */
    #[Authorize('viewAny', Session::class)]
    public function index(OnlineSessionIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $sessions = QueryBuilder::for(Session::query()->online()->visibleTo($actor)->with('user'), $request)
            ->allowedFilters(
                AllowedFilter::exact('user_id'),
                AllowedFilter::callback('username', fn (Builder $query, mixed $value) => $query->whereHas(
                    'user',
                    fn (Builder $users) => $users->whereLike('username', '%'.(is_scalar($value) ? $value : '').'%'),
                )),
                AllowedFilter::partial('ip_address'),
            )
            ->allowedSorts('last_activity')
            ->defaultSort('-last_activity')
            ->paginate($request->pageSize());

        return OnlineSessionResource::collection($sessions);
    }

    /**
     * 强制下线：删除该会话，对方的下一次请求即为 401。
     */
    #[Authorize('delete', 'session')]
    public function destroy(Session $session, LogoutSession $action): void
    {
        $action->handle($session);
    }
}
