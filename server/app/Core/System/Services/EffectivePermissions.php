<?php

namespace App\Core\System\Services;

use App\Core\System\Models\{Permission, User};
use Illuminate\Container\Attributes\Scoped;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * 有效权限：请求级的权限码计算器（设计文档 §8.1）。Gate::before、`/auth/codes`、`/menu/all` 都用它，三处一致。
 * 它不是扩展点，也不是注册表（声明在 MenuRegistry 中，节点在 permissions 表中）。
 *
 * - 节点：从 spatie 的 PermissionRegistrar 读取（spatie 缓存全部列与节点的角色，不额外查库）；
 * - 生效节点：节点本身及全部祖先都启用（停用向子孙级联，超管也受约束）；
 * - 有效权限码：普通用户要求节点本身及全部祖先都生效，且都授予了用户的启用角色；超管为全部生效节点。
 * 树的遍历、防环与“节点及全部祖先”的规则在 PermissionHierarchy（纯计算），这里只负责取节点、按用户缓存结果。
 *
 * `#[Scoped]`：每个请求（队列任务）一个实例，计算结果只在本请求内复用，常驻内存下不跨请求保存数据（§10.3）。
 * spatie 的权限缓存被清除（节点、角色、授权变更时 spatie 自动清除）后，下次调用按新数据重新计算。
 */
#[Scoped]
class EffectivePermissions
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

    private ?PermissionHierarchy $hierarchy = null;

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
     * 全部已登记的权限码（含停用节点）。
     *
     * @return list<string>
     */
    public function registeredCodes(): array
    {
        return array_keys($this->nodes());
    }

    /**
     * 补齐祖先：$codes 及其每个节点的全部祖先节点（未登记的权限码忽略），按节点顺序去重（保存角色权限时使用，§8.5）。
     *
     * @param  iterable<string>  $codes
     * @return list<string>
     */
    public function withAncestors(iterable $codes): array
    {
        $nodes = $this->nodes();
        $ids = [];

        foreach ($codes as $code) {
            if (isset($nodes[$code])) {
                $ids[] = $nodes[$code]->id;
            }
        }

        $result = $this->hierarchy()->withAncestors($ids);

        return array_values(array_filter(array_keys($nodes), fn (string $code): bool => isset($result[$nodes[$code]->id])));
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
     * 用户的有效权限码（目录、菜单、按钮都含）：节点本身及全部祖先都生效，且都授予了用户的启用角色。
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
        $granted = fn (Permission $node): bool => isset($this->enabled[$node->name])
            && $node->roles->contains(fn (SpatieRole $role): bool => in_array($role->getKey(), $roles, true));

        return $this->codes[$key] = array_values(array_map(
            fn (Permission $node): string => $node->name,
            array_filter($nodes, fn (Permission $node): bool => $this->hierarchy()->everyInChain($node->id, $granted)),
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
        $visible = array_filter(
            array_map(fn (string $code): Permission => $nodes[$code], $this->codesFor($user)),
            fn (Permission $node): bool => $node->type->isRoute(),
        );

        return PermissionHierarchy::tree($visible, pruneEmptyCatalogs: true);
    }

    /**
     * 授权用的节点树（`/system/roles/permission-tree`，§6.5）：全部生效节点（含按钮，本身及全部祖先都启用），
     * 同级按 sort、id 排序。返回根节点，children 关联已组装（节点为副本，不影响缓存）。
     *
     * @return list<Permission>
     */
    public function enabledTree(): array
    {
        return PermissionHierarchy::tree($this->hierarchy()->effectiveNodes());
    }

    private function hierarchy(): PermissionHierarchy
    {
        $this->nodes();

        return $this->hierarchy ??= PermissionHierarchy::from([]);
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

        $nodes = [];

        foreach ($permissions as $permission) {
            if ($permission instanceof Permission) {
                $nodes[$permission->name] = $permission;
            }
        }

        $this->source = $first instanceof Permission ? $first : null;
        $this->hierarchy = PermissionHierarchy::from($nodes);
        $this->codes = [];
        $this->enabled = [];

        foreach ($this->hierarchy->effectiveNodes() as $node) {
            $this->enabled[$node->name] = true;
        }

        return $this->nodes = $nodes;
    }
}
