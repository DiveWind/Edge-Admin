<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Models\LoginLog;
use App\Core\Monitor\Queries\LoginLogList;
use App\Core\System\Models\User;
use App\Http\Admin\Monitor\Requests\LoginLogIndexRequest;
use App\Http\Admin\Monitor\Resources\LoginLogResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 登录日志（设计文档 §6.6、§7.3）：列表、详情。只读，过期日志由 activitylog:clean 清理。
 *
 * 授权由 LoginLogPolicy 判定（monitor.login-log；数据范围委托给事件涉及的账号）。
 */
class LoginLogController
{
    /**
     * 列表（经过数据范围 visibleTo($actor)）：筛选 user_id、event、reason、request_id（精确；event、reason 可用逗号传多个，
     * 如失败的登录 `filter[event]=failed,lockout`），username、ip_address（模糊），created_at（区间）；排序 id、created_at，默认 `-id`。
     */
    #[Authorize('viewAny', LoginLog::class)]
    public function index(LoginLogIndexRequest $request, LoginLogList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return LoginLogResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 详情（字段同列表）。
     */
    #[Authorize('view', 'login_log')]
    public function show(LoginLog $loginLog): LoginLogResource
    {
        return new LoginLogResource($loginLog);
    }
}
