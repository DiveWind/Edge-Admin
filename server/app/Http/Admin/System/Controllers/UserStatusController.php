<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\ChangeUserStatus;
use App\Core\System\Models\User;
use App\Http\Admin\System\Requests\UserStatusRequest;
use App\Http\Admin\System\Resources\UserResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 启用 / 停用用户（PUT /system/users/{user}/status，设计文档 §6.5、§8.6）：`{ status }`。
 * 停用自己、非超管启停超管 403（UserPolicy）；最后一个启用的超管 422（status）；停用后对方的会话与 token 立即失效。
 */
class UserStatusController
{
    /**
     * Handle the incoming request.
     */
    #[Authorize('changeStatus', 'user')]
    public function __invoke(UserStatusRequest $request, User $user, ChangeUserStatus $action, #[CurrentUser] User $actor): UserResource
    {
        return new UserResource($action->handle($user, $request->status(), $actor)->load('roles'));
    }
}
