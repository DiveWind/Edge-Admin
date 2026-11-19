<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\DataScopeProvider;
use App\Core\System\Models\{Role, User};
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;
use NoDiscard;

/**
 * 已登记的数据范围提供者（设计文档 §8.4）：单例，只保存启动时注册的提供者（无状态的声明），不保存请求数据（§10.3）。
 *
 * SystemServiceProvider 注册 Core 默认的 `all`、`self`；业务模块在自己 Provider 的 boot() 中注册新的范围。
 * 角色的 data_scope 只能是已登记的 key（角色表单校验）；库中残留的未登记 key 在判定时按“拒绝”处理（HasDataScope）。
 *
 * 数据范围的防提权（§8.5）：grantableKeysFor() 是操作者“可授予的数据范围”，角色管理（CreateRole、UpdateRole）、
 * 分配角色（SyncUserRoles）与角色表单的选项（/system/roles/data-scopes）共用。
 */
#[Singleton]
class DataScopeRegistry
{
    /**
     * 不限制范围的 key：超管或任一启用角色选用它时，不再合并其他范围。
     */
    public const string ALL = 'all';

    /**
     * 新建角色默认的范围（roles.data_scope 的列默认值）。
     */
    public const string SELF = 'self';

    /**
     * key => 提供者（按注册顺序）。
     *
     * @var array<string, DataScopeProvider>
     */
    private array $providers = [];

    /**
     * 登记提供者。key 为空、不是小写标识或与已登记的重复时抛出异常：这是开发期错误。
     *
     * @throws InvalidArgumentException
     */
    public function register(DataScopeProvider ...$providers): void
    {
        foreach ($providers as $provider) {
            $key = $provider->key();

            if (preg_match('/^[a-z0-9_-]{1,64}$/', $key) !== 1) {
                throw new InvalidArgumentException('数据范围 ['.$provider::class."] 的 key “{$key}”不合法：应为 1–64 位小写字母、数字、下划线或短横线。");
            }

            if (isset($this->providers[$key])) {
                throw new InvalidArgumentException("数据范围 key “{$key}”重复：[".$provider::class.'] 与 ['.$this->providers[$key]::class.']。');
            }

            $this->providers[$key] = $provider;
        }
    }

    /**
     * key 是否已登记。
     */
    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * key 对应的提供者；未登记时返回 null。
     */
    public function get(string $key): ?DataScopeProvider
    {
        return $this->providers[$key] ?? null;
    }

    /**
     * 全部提供者，以 key 为键（按注册顺序）。
     *
     * @return array<string, DataScopeProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * 全部已登记的 key（按注册顺序）。
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->providers);
    }

    /**
     * $user 可授予的数据范围（§8.5），按登记顺序：
     *
     * - 超管，或任一启用角色的范围是 `all` → 全部已登记的范围；
     * - 否则为各启用角色选用的、已登记的范围（停用角色不算，未登记的 key 不算）。
     *
     * 只读取用户的角色，不保存任何状态（本类是单例）。
     *
     * @return list<string>
     */
    #[NoDiscard]
    public function grantableKeysFor(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return $this->keys();
        }

        $keys = $user->enabledRoles()->map(fn (Role $role): string => $role->data_scope)->all();

        if (in_array(self::ALL, $keys, true)) {
            return $this->keys();
        }

        return array_values(array_intersect($this->keys(), $keys));
    }

    /**
     * $user 能否授予范围 $key（grantableKeysFor() 中有它）。
     */
    #[NoDiscard]
    public function canGrant(User $user, string $key): bool
    {
        return in_array($key, $this->grantableKeysFor($user), true);
    }
}
