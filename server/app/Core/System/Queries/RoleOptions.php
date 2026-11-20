<?php

namespace App\Core\System\Queries;

use App\Core\System\Enums\Status;
use App\Core\System\Models\Role;

/**
 * 角色下拉的选项（设计文档 §6.5、§6.9）：`[{ value: 标识, label: 显示名 }]`，只含启用角色，按 sort、id 排序。
 *
 * 用户表单（GET /system/users/role-options）与公告表单（GET /message/announcements/role-options）共用；
 * 授权由使用它的表单所属资源的 Policy 决定（`viewFormOptions`），这里不判定。
 */
class RoleOptions
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function get(): array
    {
        $roles = Role::query()
            ->where('status', Status::Enabled)
            ->orderBy('sort')
            ->orderBy('id')
            ->get(['id', 'name', 'title']);

        return array_values($roles->map(fn (Role $role): array => ['value' => $role->name, 'label' => $role->title])->all());
    }
}
