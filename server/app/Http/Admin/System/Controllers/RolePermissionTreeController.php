<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Models\Role;
use App\Core\System\Services\EffectivePermissions;
use App\Http\Admin\System\Resources\PermissionTreeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 角色表单的授权树（GET /system/roles/permission-tree，设计文档 §6.5）：全部生效节点（本身及全部祖先都启用，含按钮），
 * 同级按 sort、id 排序，精简字段 `{ name, type, meta: { title, icon }, children }`。
 * 节点来自 EffectivePermissions（spatie 的权限缓存），不额外查库。需要角色的新增或修改权限。
 */
class RolePermissionTreeController
{
    /**
     * Handle the incoming request.
     */
    #[Authorize('viewFormOptions', Role::class)]
    public function __invoke(EffectivePermissions $permissions): AnonymousResourceCollection
    {
        return PermissionTreeResource::collection($permissions->enabledTree());
    }
}
