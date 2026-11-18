<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Models\User;
use App\Core\System\Services\PermissionRegistry;
use App\Http\Admin\System\Resources\MenuRouteResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 当前用户可见的菜单树（GET /menu/all，设计文档 §6.2）：结构与 Vben 的路由一致，前端以 mixed 模式按 name 合并。
 * 只含有效权限码中的目录、菜单、内嵌页与外链（不含按钮），没有可见子节点的目录剔除，同级按 meta.order 排序。
 *
 * 只需登录，不需要权限码。
 */
class MenuAllController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(#[CurrentUser] User $user, PermissionRegistry $permissions): AnonymousResourceCollection
    {
        return MenuRouteResource::collection($permissions->menuTreeFor($user));
    }
}
