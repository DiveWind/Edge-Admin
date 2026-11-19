<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\ChangeRoleStatus;
use App\Core\System\Models\Role;
use App\Http\Admin\System\Requests\RoleStatusRequest;
use App\Http\Admin\System\Resources\RoleResource;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 启用 / 停用角色（PUT /system/roles/{role}/status，设计文档 §6.5）：`{ status }`。
 * 停用 super-admin 返回 422（status）；停用后该角色的权限与数据范围立即不生效。
 */
class RoleStatusController
{
    /**
     * Handle the incoming request.
     */
    #[Authorize('changeStatus', 'role')]
    public function __invoke(RoleStatusRequest $request, Role $role, ChangeRoleStatus $action): RoleResource
    {
        return new RoleResource($action->handle($role, $request->status()));
    }
}
