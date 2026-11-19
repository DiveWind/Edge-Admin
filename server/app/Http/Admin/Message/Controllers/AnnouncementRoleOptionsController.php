<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Models\Announcement;
use App\Core\System\Enums\Status;
use App\Core\System\Models\Role;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 公告的目标角色下拉（GET /message/announcements/role-options，设计文档 §6.9）：`[{ value: 标识, label: 显示名 }]`，
 * 只含启用角色，按 sort、id 排序。与 /system/roles/options 相同，但按公告的新增或修改权限授权（能写公告的人不一定能管理用户）。
 */
class AnnouncementRoleOptionsController
{
    /**
     * Handle the incoming request.
     *
     * @return list<array{value: string, label: string}>
     */
    #[Authorize('viewFormOptions', Announcement::class)]
    public function __invoke(): array
    {
        $roles = Role::query()
            ->where('status', Status::Enabled)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'title']);

        return array_values($roles->map(fn (Role $role): array => ['value' => $role->name, 'label' => $role->title])->all());
    }
}
