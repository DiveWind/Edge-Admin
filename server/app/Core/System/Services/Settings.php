<?php

namespace App\Core\System\Services;

use App\Core\System\Contracts\SettingRegistry;
use App\Core\System\Data\{SettingData, SettingGroupData, SettingValueData};
use App\Core\System\Enums\SettingType;
use App\Core\System\Models\Setting;
use App\Support\Cache\VersionedCache;
use InvalidArgumentException;
use LogicException;

/**
 * 系统参数的读取（设计文档 §6.7）：给代码用的唯一方式（注入或 resolve()，不提供全局 helper 与 facade）。
 *
 * ```php
 * $limit = resolve(Settings::class)->integer('auth.login_rate_limit');
 * ```
 *
 * - 只能读取已声明的参数（SettingRegistry），未声明的键抛出 InvalidArgumentException；
 * - 值：库中管理员改过的值（类型与声明一致时）→ 否则为声明的默认值（`default`，或读取 `config` 键的当前值）；
 * - 库中的值整体缓存（带版本号的键 `CACHE_KEY:<版本>`，VersionedCache），经 Cache::memo() 读取：同一请求（队列任务）内只访问一次缓存存储；
 *   参数写入后（事务提交后）由 SettingObserver 调用 flush()，同步命令结束时也调用。
 *
 * 不保存任何状态：每次调用都取 Cache::memo()（它是 scoped 绑定，每个请求重置），常驻内存安全（§10.3）。
 */
class Settings
{
    public const string CACHE_KEY = 'core:settings';

    public function __construct(private readonly SettingRegistry $registry) {}

    /**
     * 参数的当前值（类型见声明的 type）。
     *
     * @throws InvalidArgumentException 参数未声明
     */
    public function get(string $key): mixed
    {
        return $this->entry($this->declaration($key))->value;
    }

    /**
     * string / text 类型的参数。
     *
     * @throws InvalidArgumentException|LogicException
     */
    public function string(string $key): string
    {
        $value = $this->typed($key, SettingType::String, SettingType::Text);

        return is_string($value) ? $value : throw new LogicException("系统参数 {$key} 的值不是字符串。");
    }

    /**
     * int 类型的参数。
     *
     * @throws InvalidArgumentException|LogicException
     */
    public function integer(string $key): int
    {
        $value = $this->typed($key, SettingType::Integer);

        return is_int($value) ? $value : throw new LogicException("系统参数 {$key} 的值不是整数。");
    }

    /**
     * float 类型的参数。
     *
     * @throws InvalidArgumentException|LogicException
     */
    public function float(string $key): float
    {
        $value = $this->typed($key, SettingType::Float);

        return is_float($value) ? $value : throw new LogicException("系统参数 {$key} 的值不是数字。");
    }

    /**
     * bool 类型的参数。
     *
     * @throws InvalidArgumentException|LogicException
     */
    public function boolean(string $key): bool
    {
        $value = $this->typed($key, SettingType::Boolean);

        return is_bool($value) ? $value : throw new LogicException("系统参数 {$key} 的值不是布尔值。");
    }

    /**
     * json 类型的参数。
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidArgumentException|LogicException
     */
    public function array(string $key): array
    {
        $value = $this->typed($key, SettingType::Json);

        return is_array($value) ? $value : throw new LogicException("系统参数 {$key} 的值不是数组。");
    }

    /**
     * 声明为公开（public）的参数 `{ 键: 值 }`：以代码中的声明为准，不读库中的 is_public（公开参数接口，§6.7）。
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        $values = [];

        foreach ($this->registry->all() as $key => $setting) {
            if ($setting->public) {
                $values[$key] = $this->entry($setting)->value;
            }
        }

        return $values;
    }

    /**
     * 一个分组中全部参数的当前状态（按声明顺序）。
     *
     * @return list<SettingValueData>
     */
    public function entries(SettingGroupData $group): array
    {
        return array_values(array_map($this->entry(...), $group->settings));
    }

    /**
     * 参数的当前状态：当前值、默认值、是否改过。
     */
    public function entry(SettingData $setting): SettingValueData
    {
        $stored = $this->stored()[$setting->key] ?? null;
        $default = $setting->defaultValue();
        $customized = $stored !== null && $setting->type->accepts($stored);

        return new SettingValueData(
            setting: $setting,
            value: $customized ? $setting->type->cast($stored) : $default,
            default: $default,
            customized: $customized,
        );
    }

    /**
     * 清除缓存：换一个新版本（本请求的 memo 一并更新），之后的读取从库中重新加载。
     */
    public function flush(): void
    {
        self::cache()->flush();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function declaration(string $key): SettingData
    {
        return $this->registry->get($key) ?? throw new InvalidArgumentException("系统参数 {$key} 未声明：请在模块的 resources/settings.php 中声明（设计文档 §6.7）。");
    }

    /**
     * 读取并检查声明的类型。
     *
     * @throws InvalidArgumentException|LogicException
     */
    private function typed(string $key, SettingType ...$types): mixed
    {
        $setting = $this->declaration($key);

        if (! in_array($setting->type, $types, true)) {
            throw new LogicException("系统参数 {$key} 声明为 {$setting->type->value} 类型。");
        }

        return $this->entry($setting)->value;
    }

    /**
     * 库中改过的值（JSON 解码后），以键为键；没有改过的参数不在其中。
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        /** @var array<string, mixed> */
        return self::cache()->remember(fn (): array => Setting::query()
            ->whereNotNull('value')
            ->get(['key', 'value'])
            ->mapWithKeys(fn (Setting $setting): array => [$setting->key => $setting->value])
            ->all());
    }

    /**
     * 带版本号的缓存（键 CACHE_KEY）：flush() 换新版本，避免未命中回填的竞态把旧值永久写回（设计文档 §6.7）。
     */
    public static function cache(): VersionedCache
    {
        return new VersionedCache(self::CACHE_KEY);
    }
}
