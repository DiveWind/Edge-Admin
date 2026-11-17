<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Cache\CacheManager;
use Illuminate\Database\QueryException;

/**
 * 系统参数读取入口（亦可用 setting('key', $default) 辅助函数）：整体缓存，参数变更时由 SettingObserver 清除。
 *
 * 通过 Cache::memo() 读取：同一请求 / 队列任务内只访问一次缓存存储（memo 为 scoped 绑定，每个请求 / 任务重置）。
 */
class Settings
{
    public const string CACHE_KEY = 'edge:settings';

    public function __construct(protected CacheManager $cache) {}

    /**
     * 读取参数值；参数不存在或值为 null 时返回 $default。
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key]['value'] ?? $default;
    }

    public function integer(string $key, int $default): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * 允许免登录读取的参数（`is_public`），形如 {key: value}。
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        return collect($this->all())
            ->filter(fn (array $setting): bool => $setting['is_public'])
            ->map(fn (array $setting): mixed => $setting['value'])
            ->all();
    }

    /**
     * @return array<string, array{value: mixed, is_public: bool}>
     */
    public function all(): array
    {
        try {
            return $this->cache->memo()->rememberForever(self::CACHE_KEY, fn (): array => Setting::query()
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn (Setting $setting): array => [
                    $setting->key => ['value' => $setting->value, 'is_public' => $setting->is_public],
                ])
                ->all());
        } catch (QueryException) {
            // 尚未执行迁移（如首次安装）时退回各处的默认值。
            return [];
        }
    }

    public function flush(): void
    {
        $this->cache->memo()->forget(self::CACHE_KEY);
    }
}
