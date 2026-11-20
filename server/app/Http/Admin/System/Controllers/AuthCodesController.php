<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Models\User;
use App\Core\System\Services\EffectivePermissions;
use Illuminate\Container\Attributes\CurrentUser;

/**
 * 当前用户的有效权限码（GET /auth/codes，设计文档 §6.2）：目录、菜单、按钮都含，口径见 §8.1（EffectivePermissions）。
 * Vben 用于按钮权限（v-access:code），登录时和启动时都重新拉取。
 *
 * 只需登录，不需要权限码。
 */
class AuthCodesController
{
    /**
     * Handle the incoming request.
     *
     * @return list<string>
     */
    public function __invoke(#[CurrentUser] User $user, EffectivePermissions $permissions): array
    {
        return $permissions->codesFor($user);
    }
}
