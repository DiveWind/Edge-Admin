<?php

namespace App\Core\System\Actions;

use App\Core\System\Data\MenuNodeData;
use App\Core\System\Enums\Status;
use App\Core\System\Models\{Permission, Role};
use App\Core\System\Services\MenuRegistry;
use DomainException;
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
 *
 * path 唯一（§8.2）：写入前先检查，声明节点的 path 已被另一个不在声明中的节点（自定义节点、已转为自定义的节点）占用时，
 * 抛出 DomainException 列出全部冲突，什么都不写入。声明之间互换 path 时先清空要变更的 path 再写入，不会撞上唯一索引。
 * 全部写入在一个事务中：任何一步失败都整体回滚。
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

            $this->ensureNoPathConflicts($existing);
            $this->releaseChangedPaths($existing);

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
     * 声明节点的 path 不能被不在声明中的节点占用（声明之间的 path 已由 MenuRegistry 保证互不相同）。
     *
     * @param  array<string, Permission>  $existing
     *
     * @throws DomainException
     */
    private function ensureNoPathConflicts(array $existing): void
    {
        $occupants = [];

        foreach ($existing as $name => $permission) {
            if ($permission->path !== null && ! $this->menus->has($name)) {
                $occupants[$permission->path] = $name;
            }
        }

        $conflicts = [];

        foreach ($this->menus->all() as $name => $node) {
            if ($node->path !== null && isset($occupants[$node->path])) {
                $conflicts[] = "  - {$name} 的 path {$node->path} 已被节点 {$occupants[$node->path]}（id {$existing[$occupants[$node->path]]->id}）占用";
            }
        }

        if ($conflicts !== []) {
            throw new DomainException(implode("\n", [
                '菜单声明的 path 与已有节点冲突，本次同步没有写入任何数据：',
                ...$conflicts,
                '请在菜单管理中修改（或删除）占用这些 path 的节点，然后重新执行 php artisan core:sync-permissions。',
            ]));
        }
    }

    /**
     * 先清空 path 将要变更的已有声明节点的 path：声明之间互换 path 时，逐个写入不会撞上唯一索引（内存中的模型保留原值，照常判断是否修正）。
     *
     * @param  array<string, Permission>  $existing
     */
    private function releaseChangedPaths(array $existing): void
    {
        $changed = array_keys(array_filter(
            $this->menus->all(),
            fn (MenuNodeData $node, string $name): bool => isset($existing[$name]) && $existing[$name]->path !== null && $existing[$name]->path !== $node->path,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changed !== []) {
            Permission::query()->whereIn('name', $changed)->update(['path' => null]);
        }
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
