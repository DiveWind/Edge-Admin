<?php

namespace App\Core\System\Actions;

use App\Core\System\Contracts\MenuRegistry;
use App\Core\System\Data\MenuNodeData;
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, Role};
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * 把 MenuRegistry 中声明的节点同步到 permissions 表（设计文档 §8.2）：由 `core:sync-permissions` 调用，
 * DatabaseSeeder 与部署流程（§11.4）都执行它。幂等：再次执行没有变更。
 *
 * 1. 声明中有、数据库中没有的节点：新增并标记 is_builtin；上级按 name 查找（管理员移动过上级也能找到）。
 *    数据库中已有同名的自定义节点时直接收编：标记 is_builtin 并按第 2 条修正结构性字段；
 * 2. 已有节点只修正结构性字段：type、path、component、meta.link、meta.iframeSrc；title、icon、sort、status、parent_id
 *    与 meta 的其他键（hide* 等）以数据库为准，管理员的调整不被覆盖；
 * 3. 从声明中移除的内置节点：取消 is_builtin，转为自定义节点并保留授权；不删除任何节点；
 * 4. 确保 super-admin 角色存在；
 * 5. 最后清除 spatie 的权限缓存。
 */
class SyncPermissions
{
    public function __construct(
        private readonly MenuRegistry $menus,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * Execute the action.
     *
     * @return array{created: list<string>, updated: list<string>, adopted: list<string>, released: list<string>, super_admin_created: bool}
     */
    public function handle(): array
    {
        $result = DB::transaction(function (): array {
            $result = ['created' => [], 'updated' => [], 'adopted' => [], 'released' => [], 'super_admin_created' => false];

            /** @var array<string, Permission> $existing */
            $existing = Permission::query()->get()->keyBy('name')->all();

            foreach ($this->menus->all() as $name => $node) {
                $permission = $existing[$name] ?? null;

                if ($permission === null) {
                    $existing[$name] = $this->create($node, $existing);
                    $result['created'][] = $name;

                    continue;
                }

                $adopted = ! $permission->is_builtin;

                $this->fillStructure($permission, $node);
                $permission->is_builtin = true;

                if ($adopted) {
                    $result['adopted'][] = $name;
                } elseif ($permission->isDirty()) {
                    $result['updated'][] = $name;
                }

                $permission->save();
            }

            foreach ($existing as $name => $permission) {
                if ($permission->is_builtin && ! $this->menus->has($name)) {
                    $permission->forceFill(['is_builtin' => false])->save();
                    $result['released'][] = $name;
                }
            }

            $result['super_admin_created'] = $this->ensureSuperAdmin();

            return $result;
        });

        $this->registrar->forgetCachedPermissions();

        return $result;
    }

    /**
     * 新增内置节点：声明中的全部字段生效，默认启用。
     *
     * @param  array<string, Permission>  $existing  已有节点（含本次新增的），以权限码为键
     */
    private function create(MenuNodeData $node, array $existing): Permission
    {
        $permission = new Permission;

        $permission->forceFill([
            'parent_id' => $node->parent === null ? null : $existing[$node->parent]->id,
            'name' => $node->name,
            'title' => $node->title,
            'icon' => $node->icon,
            'sort' => $node->sort,
            'status' => Status::Enabled,
            'meta' => $node->meta === [] ? null : $node->meta,
            'is_builtin' => true,
        ]);

        $this->fillStructure($permission, $node);
        $permission->save();

        return $permission;
    }

    /**
     * 结构性字段以声明为准：type、path、component，以及 meta 中的 link、iframeSrc（声明中没有的键从 meta 中去掉）。
     */
    private function fillStructure(Permission $permission, MenuNodeData $node): void
    {
        $meta = array_diff_key($permission->meta ?? [], array_flip(MenuNodeData::STRUCTURAL_META_KEYS)) + $node->structuralMeta();

        $permission->forceFill([
            'type' => $node->type,
            'path' => $node->path,
            'component' => $node->component,
        ]);

        if ($meta != ($permission->meta ?? [])) {
            $permission->meta = $meta === [] ? null : $meta;
        }
    }

    /**
     * 确保 super-admin 角色存在（权限隐含全部，不需要授权），返回是否新建。
     */
    private function ensureSuperAdmin(): bool
    {
        if (Role::query()->where('name', Role::SUPER_ADMIN)->exists()) {
            return false;
        }

        new Role()->forceFill([
            'name' => Role::SUPER_ADMIN,
            'title' => __('Super Admin'),
            'status' => Status::Enabled,
            'data_scope' => 'all',
            'remark' => null,
            'sort' => 0,
        ])->save();

        return true;
    }
}
