<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Models\Announcement;
use App\Core\System\Queries\RoleOptions;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 公告表单的目标角色下拉（GET /message/announcements/role-options，设计文档 §6.9）：`[{ value: 标识, label: 显示名 }]`，
 * 只含启用角色，按 sort、id 排序（RoleOptions）。与用户表单的 /system/users/role-options 数据相同，但按公告的新增或修改权限授权
 * （AnnouncementPolicy::viewFormOptions：能写公告的人不一定能管理用户）。
 */
class AnnouncementRoleOptionsController
{
    /**
     * Handle the incoming request.
     *
     * @return list<array{value: string, label: string}>
     */
    #[Authorize('viewFormOptions', Announcement::class)]
    public function __invoke(RoleOptions $options): array
    {
        return $options->get();
    }
}
