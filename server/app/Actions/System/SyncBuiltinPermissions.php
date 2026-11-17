<?php

namespace App\Actions\System;

use App\Enums\PermissionType;
use App\Models\Permission;
use App\Services\PermissionRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * 把 config/permissions.php 声明的内置菜单权限同步到 permissions 表（幂等）。
 *
 * - 缺失的节点：按配置新增（上级按 code 查找，含界面上移动过的上级），标记 is_system；
 * - 已存在的节点：只修正结构性字段 type / path / is_external 并标记 is_system，
 *   name / icon / sort / is_visible / status / parent_id / remark 以数据库为准（管理员在界面上的修改不被覆盖）；
 * - 不再删除任何节点：已从配置移除的内置节点解除 is_system（转为自定义节点，保留角色授权），由管理员决定是否删除。
 */
class SyncBuiltinPermissions
{
    public function __construct(protected PermissionRegistry $registry) {}

    /**
     * @param  list<array<string, mixed>>  $tree  config('permissions')
     * @return array{declared: int, created: int, updated: int, released: list<string>}
     */
    public function handle(array $tree): array
    {
        return DB::transaction(function () use ($tree): array {
            $existing = Permission::query()->get()->keyBy('code');
            $result = ['declared' => 0, 'created' => 0, 'updated' => 0, 'released' => []];
            $declared = [];

            $this->syncNodes($tree, null, $existing, $declared, $result);

            $released = Permission::query()
                ->where('is_system', true)
                ->whereNotIn('code', $declared)
                ->orderBy('id')
                ->pluck('code')
                ->all();

            Permission::query()->whereIn('code', $released)->update(['is_system' => false]);
            $this->registry->flush();

            return [...$result, 'declared' => count($declared), 'released' => array_values($released)];
        });
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  Collection<string, Permission>  $existing
     * @param  list<string>  $declared
     * @param  array{declared: int, created: int, updated: int, released: list<string>}  $result
     */
    protected function syncNodes(array $nodes, ?Permission $parent, Collection $existing, array &$declared, array &$result): void
    {
        foreach ($nodes as $index => $node) {
            /** @var list<array<string, mixed>> $children */
            $children = Arr::array($node, 'children', []);
            $type = PermissionType::from(Arr::string($node, 'type', $children !== [] ? PermissionType::Directory->value : PermissionType::Menu->value));

            // Arr::string() / boolean() 在配置值类型不对时直接抛异常，配置写错不会被静默转换后写库。
            $permission = $this->upsert($existing, $declared, $result, [
                'code' => Arr::string($node, 'code'),
                'name' => Arr::string($node, 'name'),
                'type' => $type,
                'path' => isset($node['path']) ? Arr::string($node, 'path') : null,
                'icon' => isset($node['icon']) ? Arr::string($node, 'icon') : null,
                'is_external' => Arr::boolean($node, 'external', false),
                'is_visible' => Arr::boolean($node, 'visible', true),
                'sort' => $index,
            ], $parent);

            /** @var array<string, string> $actions */
            $actions = Arr::array($node, 'actions', []);

            foreach (array_keys($actions) as $actionIndex => $action) {
                $this->upsert($existing, $declared, $result, [
                    'code' => "{$permission->code}.{$action}",
                    'name' => $actions[$action],
                    'type' => PermissionType::Action,
                    'path' => null,
                    'icon' => null,
                    'is_external' => false,
                    'is_visible' => true,
                    'sort' => $actionIndex,
                ], $permission);
            }

            $this->syncNodes($children, $permission, $existing, $declared, $result);
        }
    }

    /**
     * @param  Collection<string, Permission>  $existing
     * @param  list<string>  $declared
     * @param  array{declared: int, created: int, updated: int, released: list<string>}  $result
     * @param  array{code: string, name: string, type: PermissionType, path: string|null, icon: string|null, is_external: bool, is_visible: bool, sort: int}  $definition
     */
    protected function upsert(Collection $existing, array &$declared, array &$result, array $definition, ?Permission $parent): Permission
    {
        $declared[] = $definition['code'];
        $permission = $existing->get($definition['code']);

        if ($permission === null) {
            $permission = new Permission([...$definition, 'parent_id' => $parent?->id]);
            $permission->forceFill(['is_system' => true])->save();
            $existing->put($permission->code, $permission);
            $result['created']++;

            return $permission;
        }

        $permission->forceFill([
            'type' => $definition['type'],
            'path' => $definition['path'],
            'is_external' => $definition['is_external'],
            'is_system' => true,
        ]);

        if ($permission->isDirty()) {
            $permission->save();
            $result['updated']++;
        }

        return $permission;
    }
}
