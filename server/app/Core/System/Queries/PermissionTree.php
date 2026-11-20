<?php

namespace App\Core\System\Queries;

use App\Core\System\Models\Permission;
use App\Core\System\Services\PermissionHierarchy;

/**
 * 菜单管理用的完整节点树（GET /system/menus，设计文档 §6.5）：全部节点，含停用节点与按钮，同级按 sort、id 排序。
 *
 * 一次查询取出全部节点，在内存中组装（PermissionHierarchy::tree()）：每个节点的 children 关联都已设置（叶子为空集合），
 * 输出时不再查库（没有 N+1）。与 EffectivePermissions 的树不同，这里不看启用状态与授权：菜单管理要能看到并重新启用停用的节点。
 */
class PermissionTree
{
    /**
     * Execute the query: 根节点列表。
     *
     * @return list<Permission>
     */
    public function get(): array
    {
        return PermissionHierarchy::tree(Permission::query()->get());
    }
}
