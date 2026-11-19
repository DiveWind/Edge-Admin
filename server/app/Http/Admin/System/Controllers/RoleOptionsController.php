<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Enums\Status;
use App\Core\System\Models\Role;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 角色下拉（GET /system/roles/options，设计文档 §6.5）：用户表单中分配角色时使用，`[{ value: 标识, label: 显示名 }]`，
 * 只含启用角色，按 sort、id 排序。需要用户的新增或修改权限。
 */
class RoleOptionsController
{
    /**
     * Handle the incoming request.
     *
     * @return list<array{value: string, label: string}>
     */
    #[Authorize('viewOptions', Role::class)]
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
