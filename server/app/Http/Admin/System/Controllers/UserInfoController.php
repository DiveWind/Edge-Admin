<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Models\User;
use App\Http\Admin\System\Resources\UserInfoResource;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * 当前登录用户的信息（GET /user/info，设计文档 §6.2）：Vben 启动时用它判断是否已登录，未登录返回 401。
 *
 * 只需登录，不需要权限码；登录、登出、修改资料与密码、两步验证等由 Fortify 提供。
 */
class UserInfoController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(#[CurrentUser] User $user): UserInfoResource
    {
        return new UserInfoResource($user);
    }
}
