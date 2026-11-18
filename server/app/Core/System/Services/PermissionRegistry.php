<?php

namespace App\Core\System\Services;

use App\Core\System\Enums\PermissionType;
use App\Core\System\Models\{Permission, User};
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * 权限码的判定口径（设计文档 §8.1）：Gate::before、`/auth/codes`、`/menu/all` 都用它，三处一致。
 *
 * - 节点：从 spatie 的 PermissionRegistrar 读取（spatie 缓存全部列与节点的角色，不额外查库）；
 * - 生效节点：节点本身及全部祖先都启用（停用向子孙级联，超管也受约束）；
 * - 有效权限码：普通用户要求节点本身及全部祖先都生效，且都授予了用户的启用角色；超管为全部生效节点。
 *
 * `#[Scoped]`：每个请求（队列任务）一个实例，计算结果只在本请求内复用，常驻内存下不跨请求保存数据（§10.3）。
 * spatie 的权限缓存被清除（节点、角色、授权变更时 spatie 自动清除）后，下次调用按新数据重新计算。
 */
#[Scoped]
class PermissionRegistry
{
    /**
     * 计算时 PermissionRegistrar 中的第一个节点：registrar 清除缓存后会重新加载出新的模型实例，据此判断计算结果是否过期。
     */
    private ?Permission $source = null;

    /**
     * 全部节点，以权限码为键。
     *
     * @var array<string, Permission>|null
     */
    private ?array $nodes = null;

    /**
     * 全部节点，以 id 为键。
     *
     * @var array<int, Permission>
     */
    private array $byId = [];

    /**
     * 生效节点（本身及全部祖先都启用）的权限码。
     *
     * @var array<string, true>
     */
    private array $enabled = [];

    /**
     * 用户 id => 有效权限码。
     *
     * @var array<array-key, list<string>>
     */
    private array $codes = [];

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * ability 是否为已登记的权限码（以 permissions 表为准，不看格式）。
     */
    public function isRegistered(string $ability): bool
    {
        return isset($this->nodes()[$ability]);
    }

    /**
     * 权限码对应的节点是否生效（本身及全部祖先都启用）。
     */
    public function isEnabled(string $code): bool
    {
        $this->nodes();

        return isset($this->enabled[$code]);
    }

    /**
     * 全部生效节点的权限码（即超管的有效权限码）。
     *
     * @return list<string>
     */
    public function enabledCodes(): array
    {
        $this->nodes();

        return array_keys($this->enabled);
    }

    /**
     * 用户的有效权限码（目录、菜单、按钮都含）。
     *
     * @return list<string>
     */
    public function codesFor(User $user): array
    {
        $nodes = $this->nodes();
        $key = $user->getKey();

        if (! is_int($key) && ! is_string($key)) {
            return [];
        }

        if (isset($this->codes[$key])) {
            return $this->codes[$key];
        }

        if ($user->isSuperAdmin()) {
            return $this->codes[$key] = array_keys($this->enabled);
        }

        $roles = $user->enabledRoles()->modelKeys();
        $effective = [];

        $isEffective = function (Permission $node) use (&$isEffective, &$effective, $roles): bool {
            if (isset($effective[$node->id])) {
                return $effective[$node->id];
            }

            // 先占位，避免脏数据成环时无限递归
            $effective[$node->id] = false;
            $parent = $node->parent_id === null ? null : ($this->byId[$node->parent_id] ?? false);

            return $effective[$node->id] = isset($this->enabled[$node->name])
                && $node->roles->contains(fn (SpatieRole $role): bool => in_array($role->getKey(), $roles, true))
                && ($parent === null || ($parent !== false && $isEffective($parent)));
        };

        return $this->codes[$key] = array_values(array_map(
            fn (Permission $node): string => $node->name,
            array_filter($nodes, $isEffective),
        ));
    }

    /**
     * 用户是否拥有权限码 $code（Gate::before 使用）。
     */
    public function allows(User $user, string $code): bool
    {
        return in_array($code, $this->codesFor($user), true);
    }

    /**
     * 用户可见的菜单树（`/menu/all`，§6.2）：只含有效权限码中的路由节点（不含按钮），没有可见子节点的目录剔除，
     * 同级按 sort、id 排序。返回根节点，children 关联已组装（节点为副本，不影响缓存）。
     *
     * @return list<Permission>
     */
    public function menuTreeFor(User $user): array
    {
        $nodes = $this->nodes();
        $visible = [];

        foreach ($this->codesFor($user) as $code) {
            $node = $nodes[$code];

            if ($node->type->isRoute()) {
                $visible[$node->parent_id ?? 0][] = $node;
            }
        }

        return $this->assemble($visible, 0);
    }

    /**
     * 从根开始逐层组装；父节点不可见的节点不会被提升为根。
     *
     * @param  array<int, list<Permission>>  $visible  上级 id（根为 0）=> 可见节点
     * @return list<Permission>
     */
    private function assemble(array $visible, int $parentId): array
    {
        $children = $visible[$parentId] ?? [];

        usort($children, fn (Permission $a, Permission $b): int => [$a->sort, $a->id] <=> [$b->sort, $b->id]);

        $tree = [];

        foreach ($children as $node) {
            $grandchildren = $this->assemble($visible, $node->id);

            if ($node->type === PermissionType::Catalog && $grandchildren === []) {
                continue;
            }

            $tree[] = (clone $node)->setRelation('children', new Collection($grandchildren));
        }

        return $tree;
    }

    /**
     * 全部节点（以权限码为键）；PermissionRegistrar 重新加载过时重新计算生效节点。
     *
     * @return array<string, Permission>
     */
    private function nodes(): array
    {
        $permissions = $this->registrar->getPermissions();
        $first = $permissions->first();

        if ($this->nodes !== null && $first === $this->source) {
            return $this->nodes;
        }

        $nodes = $byId = [];

        foreach ($permissions as $permission) {
            if ($permission instanceof Permission) {
                $nodes[$permission->name] = $byId[$permission->id] = $permission;
            }
        }

        $this->source = $first instanceof Permission ? $first : null;
        $this->byId = $byId;
        $this->enabled = $this->codes = [];

        $resolved = [];

        $isEnabled = function (Permission $node) use (&$isEnabled, &$resolved): bool {
            if (isset($resolved[$node->id])) {
                return $resolved[$node->id];
            }

            $resolved[$node->id] = false;
            $parent = $node->parent_id === null ? null : ($this->byId[$node->parent_id] ?? false);

            return $resolved[$node->id] = $node->isEnabled() && ($parent === null || ($parent !== false && $isEnabled($parent)));
        };

        foreach ($nodes as $name => $node) {
            if ($isEnabled($node)) {
                $this->enabled[$name] = true;
            }
        }

        return $this->nodes = $nodes;
    }
}
