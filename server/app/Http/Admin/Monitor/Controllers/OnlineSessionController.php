<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\LogoutSession;
use App\Core\Monitor\Models\Session;
use App\Core\Monitor\Queries\OnlineSessionList;
use App\Core\System\Models\User;
use App\Http\Admin\Monitor\Requests\OnlineSessionIndexRequest;
use App\Http\Admin\Monitor\Resources\OnlineSessionResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

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
    public function index(OnlineSessionIndexRequest $request, OnlineSessionList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return OnlineSessionResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
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
