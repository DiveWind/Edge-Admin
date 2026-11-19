<?php

namespace App\Core\System\Models\Concerns;

use App\Core\System\Contracts\DataScopeProvider;
use App\Core\System\Models\{Role, User};
use App\Core\System\Services\DataScopeRegistry;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;
use NoDiscard;

/**
 * 数据权限（设计文档 §8.4）：查询范围 `visibleTo($user)` 与单条判定 `isVisibleTo($user)`，两者规则相同，Policy 复用后者。
 *
 * 1. 超管，或任一启用角色的范围是 `all` → 不限制；
 * 2. 否则按各启用角色选用的范围（DataScopeRegistry 中的提供者）做 OR 并集，每个提供者收到选用它的启用角色；
 * 3. 没有启用角色，或角色的范围 key 未登记（该角色不贡献任何范围）→ 默认拒绝：查询加 `1 = 0`，单条判定为 false。
 *
 * 停用的角色，其权限与数据范围都不生效。所有查询入口（列表、详情、下拉选项、导出、统计、全局搜索）都必须经过它。
 *
 * @mixin Model
 */
trait HasDataScope
{
    /**
     * 只保留 $user 可见的记录：`Model::query()->visibleTo($user)`。
     *
     * @param  Builder<static>  $query
     */
    protected function scopeVisibleTo(Builder $query, User $user): void
    {
        $scopes = self::dataScopesFor($user);

        if ($scopes === null) {
            return;
        }

        if ($scopes === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $query) use ($scopes, $user): void {
            foreach ($scopes as ['provider' => $provider, 'roles' => $roles]) {
                $query->orWhere(fn (Builder $query) => $provider->constrain($query, $user, $roles));
            }
        });
    }

    /**
     * 本条记录是否对 $user 可见（与 visibleTo() 同一规则）。
     */
    #[NoDiscard]
    public function isVisibleTo(User $user): bool
    {
        $scopes = self::dataScopesFor($user);

        if ($scopes === null) {
            return true;
        }

        return array_any($scopes, fn (array $scope): bool => $scope['provider']->contains($this, $user, $scope['roles']));
    }

    /**
     * $user 适用的范围：null 表示不限制；否则为各已登记范围的提供者与选用它的启用角色（空数组表示什么都看不到）。
     *
     * @return list<array{provider: DataScopeProvider, roles: Collection<int, Role>}>|null
     */
    private static function dataScopesFor(User $user): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        $registry = resolve(DataScopeRegistry::class);
        $scopes = [];

        foreach ($user->enabledRoles()->toBase()->groupBy(fn (Role $role): string => $role->data_scope) as $key => $roles) {
            if ($key === DataScopeRegistry::ALL) {
                return null;
            }

            $provider = $registry->get((string) $key);

            if ($provider !== null) {
                /** @var Collection<int, Role> $roles */
                $scopes[] = ['provider' => $provider, 'roles' => $roles->values()];
            }
        }

        return $scopes;
    }
}
