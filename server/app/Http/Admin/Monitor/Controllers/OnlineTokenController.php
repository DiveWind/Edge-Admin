<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\RevokeToken;
use App\Core\Monitor\Queries\OnlineTokenList;
use App\Core\System\Models\User;
use App\Http\Admin\Monitor\Requests\OnlineTokenIndexRequest;
use App\Http\Admin\Monitor\Resources\OnlineTokenResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 在线会话页的 token（设计文档 §6.6、§7.3）：有效 token 的列表与吊销。
 *
 * 授权由 PersonalAccessTokenPolicy 判定（MonitorServiceProvider 中绑定）：查看 monitor.online，吊销 monitor.online.logout；
 * 不能吊销本次请求使用的 token、非超管不能吊销超管的 token（403）。
 */
class OnlineTokenController
{
    /**
     * 列表（有效、所属用户在数据范围内，ActiveTokens）：筛选 user_id（精确）、username、name（模糊）；
     * 排序 id、last_used_at、created_at，默认 `-id`。
     */
    #[Authorize('viewAny', PersonalAccessToken::class)]
    public function index(OnlineTokenIndexRequest $request, OnlineTokenList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return OnlineTokenResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 吊销：删除该 token，使用它的下一次请求即为 401。
     */
    #[Authorize('delete', 'token')]
    public function destroy(PersonalAccessToken $token, RevokeToken $action): void
    {
        $action->handle($token);
    }
}
