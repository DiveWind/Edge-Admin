<?php

namespace App\Core\System\Queries;

use App\Core\System\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

/**
 * 菜单管理用的完整节点树（GET /system/menus，设计文档 §6.5）：全部节点，含停用节点与按钮，同级按 sort、id 排序。
 *
 * 一次查询取出全部节点，在内存中组装：每个节点的 children 关联都已设置（叶子为空集合），输出时不再查库（没有 N+1）。
 * 与 PermissionRegistry 的树不同，这里不看启用状态与授权：菜单管理要能看到并重新启用停用的节点。
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
        $nodes = Permission::query()->orderBy('sort')->orderBy('id')->get();
        $children = [];

        foreach ($nodes as $node) {
            $children[$node->parent_id ?? 0][] = $node;
        }

        foreach ($nodes as $node) {
            $node->setRelation('children', new Collection($children[$node->id] ?? []));
        }

        return $children[0] ?? [];
    }
}
