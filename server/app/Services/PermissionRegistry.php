<?php

namespace App\Services;

use App\Collections\TreeCollection;
use App\Enums\PermissionType;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Collection;

/**
 * 菜单权限树的读取入口：每个请求（队列任务）只查询一次 permissions 表，其余在 PHP 侧计算。
 *
 * - 生效节点：自身与全部祖先都是启用状态（停用级联到子孙）；
 * - 菜单树：生效且可见的目录 / 菜单，祖先也必须可见，用户须拥有节点权限码，没有可见子节点的目录被剔除。
 *
 * 节点变更时由 Permission 模型事件调用 flush()；绕过模型的批量更新需要手动 flush()。
 */
#[Scoped]
class PermissionRegistry
{
    /**
     * @var TreeCollection<int, Permission>|null
     */
    protected ?TreeCollection $nodes = null;

    /**
     * 生效节点 id => true。
     *
     * @var array<int, true>|null
     */
    protected ?array $enabledIds = null;

    /**
     * 全部节点（按 sort、id 排序，不含 children 关系）。
     *
     * @return TreeCollection<int, Permission>
     */
    public function all(): TreeCollection
    {
        /** @var TreeCollection<int, Permission> $nodes */
        $nodes = $this->nodes ??= Permission::query()->ordered()->get();

        return $nodes;
    }

    /**
     * 生效节点 id（自身与全部祖先均启用）。
     *
     * @return array<int, true>
     */
    public function enabledIds(): array
    {
        if ($this->enabledIds !== null) {
            return $this->enabledIds;
        }

        $byId = $this->all()->keyBy('id');
        $resolved = [];

        $isEnabled = function (Permission $node) use (&$isEnabled, &$resolved, $byId): bool {
            if (array_key_exists($node->id, $resolved)) {
                return $resolved[$node->id];
            }

            // 先占位，避免脏数据成环时无限递归
            $resolved[$node->id] = false;
            $parent = $node->parent_id === null ? null : $byId->get($node->parent_id);

            return $resolved[$node->id] = $node->isActive() && ($parent === null || $isEnabled($parent));
        };

        return $this->enabledIds = $this->all()
            ->filter(fn (Permission $node): bool => $isEnabled($node))
            ->mapWithKeys(fn (Permission $node): array => [$node->id => true])
            ->all();
    }

    public function isEnabled(int $id): bool
    {
        return isset($this->enabledIds()[$id]);
    }

    /**
     * 生效节点（平铺）。
     *
     * @return TreeCollection<int, Permission>
     */
    public function enabled(): TreeCollection
    {
        return $this->all()->filter(fn (Permission $node): bool => $this->isEnabled($node->id))->values();
    }

    /**
     * 生效节点组成的树（节点为副本，组装 children 不影响缓存）。
     *
     * @return TreeCollection<int, Permission>
     */
    public function enabledTree(): TreeCollection
    {
        return (new TreeCollection($this->enabled()->map(fn (Permission $node): Permission => clone $node)->all()))->toTree();
    }

    /**
     * 全部生效的权限码（超级管理员拥有的权限码）。
     *
     * @return list<string>
     */
    public function enabledCodes(): array
    {
        return $this->enabled()->map(fn (Permission $node): string => $node->code)->values()->all();
    }

    /**
     * 当前用户可见的菜单树（根节点集合，children 已组装）。
     *
     * @return TreeCollection<int, Permission>
     */
    public function menuTreeFor(User $user): TreeCollection
    {
        $granted = array_flip($user->permissionCodes());

        $candidates = $this->enabled()
            ->filter(fn (Permission $node): bool => $node->type->isMenuEntry() && $node->is_visible && isset($granted[$node->code]))
            ->map(fn (Permission $node): Permission => clone $node)
            ->values();

        $childrenByParent = $candidates->groupBy(fn (Permission $node): int => $node->parent_id ?? 0);

        return new TreeCollection($this->assemble($childrenByParent->all(), 0));
    }

    /**
     * 从根开始逐层组装：父节点不在候选集中的节点（父级隐藏 / 无权限）不会被提升为根，没有子菜单的目录被剔除。
     *
     * @param  array<int, Collection<int, Permission>>  $childrenByParent
     * @return list<Permission>
     */
    protected function assemble(array $childrenByParent, int $parentId): array
    {
        $nodes = [];

        foreach ($childrenByParent[$parentId] ?? [] as $node) {
            $children = $this->assemble($childrenByParent, $node->id);

            if ($node->type === PermissionType::Directory && $children === []) {
                continue;
            }

            $node->setRelation('children', new TreeCollection($children));
            $nodes[] = $node;
        }

        return $nodes;
    }

    public function flush(): void
    {
        $this->nodes = null;
        $this->enabledIds = null;
    }
}
