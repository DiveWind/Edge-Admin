<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Models\User;
use App\Core\System\Queries\RoleOptions;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 用户表单的角色下拉（GET /system/users/role-options，设计文档 §6.5）：分配角色时使用，`[{ value: 标识, label: 显示名 }]`，
 * 只含启用角色，按 sort、id 排序（RoleOptions）。表单的选项挂在使用它的表单所属的资源下，按 UserPolicy::viewFormOptions 授权
 * （用户的新增或修改权限码）；公告表单的同类接口见 AnnouncementRoleOptionsController。
 */
class UserRoleOptionsController
{
    /**
     * Handle the incoming request.
     *
     * @return list<array{value: string, label: string}>
     */
    #[Authorize('viewFormOptions', User::class)]
    public function __invoke(RoleOptions $options): array
    {
        return $options->get();
    }
}
