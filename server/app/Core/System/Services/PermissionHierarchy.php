<?php

namespace App\Core\System\Services;

use App\Core\System\Enums\PermissionType;
use App\Core\System\Models\Permission;
use Closure;
use Illuminate\Database\Eloquent\Collection;

/**
 * 菜单树（permissions 表的节点）的纯计算（设计文档 §8.1、§8.2、§8.6）：遍历、防环、“节点及全部祖先都满足条件才算数”、组装树。
 * 树的规则只有这一份实现，有效权限码（EffectivePermissions）、菜单管理（UpdatePermission、Queries/PermissionTree）、
 * 授权用的节点树都用它。
 *
 * 不查库、不写库：由调用方传入节点（spatie 缓存中的节点，或一次查出的全部节点）。一个实例对应一组节点，计算完即丢弃，不跨请求保存。
 *
 * 脏数据一律按“不成立”处理（fail-closed）：上级不存在、上级链成环时，链不完整，everyInChain() 为 false（节点不生效）；
 * chain() 与 isSelfOrDescendant() 遇到环时停下，不会无限循环。
 */
final readonly class PermissionHierarchy
{
    /**
     * @param  array<int, Permission>  $nodes  以 id 为键
     */
    private function __construct(private array $nodes) {}

    /**
     * @param  iterable<Permission>  $nodes
     */
    public static function from(iterable $nodes): self
    {
        $byId = [];

        foreach ($nodes as $node) {
            $byId[$node->id] = $node;
        }

        return new self($byId);
    }

    /**
     * 把 $node 换成修改后的版本（同 id），返回新的实例：用来比较修改前后的树。
     */
    public function with(Permission $node): self
    {
        $nodes = $this->nodes;
        $nodes[$node->id] = $node;

        return new self($nodes);
    }

    /**
     * 全部节点，以 id 为键。
     *
     * @return array<int, Permission>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * id 对应的节点；不存在时为 null。
     */
    public function find(?int $id): ?Permission
    {
        return $id === null ? null : ($this->nodes[$id] ?? null);
    }

    /**
     * 节点及其全部祖先（id => 节点，从自身向上），遇到环或不存在的上级时停下。
     *
     * @return array<int, Permission>
     */
    public function chain(int $id): array
    {
        $chain = [];

        for ($node = $this->find($id); $node instanceof Permission && ! isset($chain[$node->id]); $node = $this->find($node->parent_id)) {
            $chain[$node->id] = $node;
        }

        return $chain;
    }

    /**
     * $id 是否为 $ancestorId 本身或其子孙（沿上级向上查找）。
     */
    public function isSelfOrDescendant(int $id, int $ancestorId): bool
    {
        return isset($this->chain($id)[$ancestorId]);
    }

    /**
     * 节点及其全部祖先都满足 $condition，且链一直通到根（没有环、没有不存在的上级）。节点不存在时为 false。
     *
     * @param  Closure(Permission): bool  $condition
     */
    public function everyInChain(int $id, Closure $condition): bool
    {
        $chain = $this->chain($id);
        $top = $chain === [] ? null : array_last($chain);

        return $top !== null && $top->parent_id === null && array_all($chain, $condition);
    }

    /**
     * 节点是否生效（§8.1）：本身及全部祖先都启用。停用向子孙级联，超管也受约束。
     */
    public function isEffective(int $id): bool
    {
        return $this->everyInChain($id, fn (Permission $node): bool => $node->isEnabled());
    }

    /**
     * 全部生效节点（按传入的顺序）。
     *
     * @return array<int, Permission>
     */
    public function effectiveNodes(): array
    {
        return array_filter($this->nodes, fn (Permission $node): bool => $this->isEffective($node->id));
    }

    /**
     * $ids 及每个节点的全部祖先的 id（不存在的 id 忽略）。
     *
     * @param  iterable<int>  $ids
     * @return array<int, true>
     */
    public function withAncestors(iterable $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            foreach (array_keys($this->chain($id)) as $ancestor) {
                $result[$ancestor] = true;
            }
        }

        return $result;
    }

    /**
     * 用 $visible 中的节点组装树：从根开始逐层组装，上级不在 $visible 中的节点不会被提升为根；同级按 sort、id 排序。
     * 返回根节点，每个节点（副本，不影响传入的模型）的 children 关联都已设置（叶子为空集合）。
     * $pruneEmptyCatalogs 为 true 时剔除没有可见子节点的目录（`/menu/all`）。
     *
     * @param  iterable<Permission>  $visible
     * @return list<Permission>
     */
    public static function tree(iterable $visible, bool $pruneEmptyCatalogs = false): array
    {
        $children = [];

        foreach ($visible as $node) {
            $children[$node->parent_id ?? 0][] = $node;
        }

        return self::assemble($children, 0, $pruneEmptyCatalogs, []);
    }

    /**
     * @param  array<int, list<Permission>>  $children  上级 id（根为 0）=> 节点
     * @param  array<int, true>  $path  正在组装的祖先（防环）
     * @return list<Permission>
     */
    private static function assemble(array $children, int $parentId, bool $pruneEmptyCatalogs, array $path): array
    {
        $nodes = $children[$parentId] ?? [];

        usort($nodes, fn (Permission $a, Permission $b): int => [$a->sort, $a->id] <=> [$b->sort, $b->id]);

        $tree = [];

        foreach ($nodes as $node) {
            if (isset($path[$node->id])) {
                continue;
            }

            $grandchildren = self::assemble($children, $node->id, $pruneEmptyCatalogs, $path + [$node->id => true]);

            if ($pruneEmptyCatalogs && $node->type === PermissionType::Catalog && $grandchildren === []) {
                continue;
            }

            $tree[] = (clone $node)->setRelation('children', new Collection($grandchildren));
        }

        return $tree;
    }
}
