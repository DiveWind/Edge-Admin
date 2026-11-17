<?php

namespace App\Services;

use App\Models\DictType;
use Illuminate\Cache\CacheManager;

/**
 * 数据字典读取入口：只包含启用类型下的启用项，按 sort 排序，整体缓存，字典变更时由 DictionaryObserver 清除。
 * 通过 Cache::memo() 读取：同一请求 / 队列任务内只访问一次缓存存储（memo 为 scoped 绑定，每个请求 / 任务重置）。
 *
 * 用法：app(Dictionary::class)->items('article_source')、->values('article_source')；
 * 校验用 new \App\Rules\InDict('article_source')。
 */
class Dictionary
{
    public const string CACHE_KEY = 'edge:dicts';

    public function __construct(protected CacheManager $cache) {}

    /**
     * 某个字典的启用项。
     *
     * @return list<array{label: string, value: string, color: string|null}>
     */
    public function items(string $code): array
    {
        return $this->all()[$code] ?? [];
    }

    /**
     * 某个字典的全部可选值，常用于校验。
     *
     * @return list<string>
     */
    public function values(string $code): array
    {
        return array_column($this->items($code), 'value');
    }

    /**
     * 某个值对应的标签，不存在时返回 null。
     */
    public function label(string $code, string $value): ?string
    {
        foreach ($this->items($code) as $item) {
            if ($item['value'] === $value) {
                return $item['label'];
            }
        }

        return null;
    }

    /**
     * 按 code 批量获取；不存在或已禁用的字典返回空数组。
     *
     * @param  list<string>  $codes
     * @return array<string, list<array{label: string, value: string, color: string|null}>>
     */
    public function many(array $codes): array
    {
        $all = $this->all();

        return collect($codes)->mapWithKeys(fn (string $code): array => [$code => $all[$code] ?? []])->all();
    }

    /**
     * @return array<string, list<array{label: string, value: string, color: string|null}>>
     */
    public function all(): array
    {
        return $this->cache->memo()->rememberForever(self::CACHE_KEY, fn (): array => $this->load());
    }

    /**
     * @return array<string, list<array{label: string, value: string, color: string|null}>>
     */
    protected function load(): array
    {
        $types = DictType::query()
            ->active()
            ->with(['items' => fn ($query) => $query->active()->ordered()])
            ->get();

        $dicts = [];

        foreach ($types as $type) {
            $dicts[$type->code] = [];

            foreach ($type->items as $item) {
                $dicts[$type->code][] = ['label' => $item->label, 'value' => $item->value, 'color' => $item->color];
            }
        }

        return $dicts;
    }

    public function flush(): void
    {
        $this->cache->memo()->forget(self::CACHE_KEY);
    }
}
