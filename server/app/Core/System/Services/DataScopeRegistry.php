<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\DataScopeProvider;
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

/**
 * 已登记的数据范围提供者（设计文档 §8.4）：单例，只保存启动时注册的提供者（无状态的声明），不保存请求数据（§10.3）。
 *
 * SystemServiceProvider 注册 Core 默认的 `all`、`self`；业务模块在自己 Provider 的 boot() 中注册新的范围。
 * 角色的 data_scope 只能是已登记的 key（角色表单校验）；库中残留的未登记 key 在判定时按“拒绝”处理（HasDataScope）。
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
}
