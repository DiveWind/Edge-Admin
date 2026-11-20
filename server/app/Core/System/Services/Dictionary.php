<?php

namespace App\Core\System\Services;

use App\Core\System\Enums\Status;
use App\Core\System\Models\DictType;
use App\Support\Cache\VersionedCache;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 字典的读取（设计文档 §6.7）：选项接口（/options/dicts）与 InDict 校验共用。
 *
 * - 只含**启用类型下的启用项**，按 sort、id 排序；不存在或已停用的类型视为没有字典项；
 * - 全部字典整体缓存（带版本号的键 `CACHE_KEY:<版本>`，VersionedCache），经 Cache::memo() 读取：同一请求（队列任务）内只访问一次缓存存储；
 * - 字典类型、字典项写入后（事务提交后）由 DictionaryObserver 调用 flush()，同步命令结束时也调用。
 *
 * 不保存任何状态：每次调用都取 Cache::memo()（它是 scoped 绑定，每个请求重置），常驻内存安全（§10.3）。
 */
class Dictionary
{
    public const string CACHE_KEY = 'core:dicts';

    /**
     * 某个类型的选项。
     *
     * @return list<array{value: string, label: string, color: ?string}>
     */
    public function options(string $type): array
    {
        return $this->all()[$type] ?? [];
    }

    /**
     * 多个类型的选项，以类型为键（按传入顺序）。
     *
     * @param  list<string>  $types
     * @return array<string, list<array{value: string, label: string, color: ?string}>>
     */
    public function many(array $types): array
    {
        $all = $this->all();

        return array_combine($types, array_map(fn (string $type): array => $all[$type] ?? [], $types));
    }

    /**
     * 某个类型的全部可选值。
     *
     * @return list<string>
     */
    public function values(string $type): array
    {
        return array_column($this->options($type), 'value');
    }

    /**
     * 值是否为该类型的启用项。
     */
    public function has(string $type, string $value): bool
    {
        return in_array($value, $this->values($type), true);
    }

    /**
     * 清除缓存：换一个新版本（本请求的 memo 一并更新），之后的读取从库中重新加载。
     */
    public function flush(): void
    {
        self::cache()->flush();
    }

    /**
     * 全部启用类型的启用项，以类型编码为键。
     *
     * @return array<string, list<array{value: string, label: string, color: ?string}>>
     */
    private function all(): array
    {
        /** @var array<string, list<array{value: string, label: string, color: ?string}>> */
        return self::cache()->remember($this->load(...));
    }

    /**
     * 从数据库加载：启用类型下的启用项，按 sort、id 排序。
     *
     * @return array<string, list<array{value: string, label: string, color: ?string}>>
     */
    private function load(): array
    {
        $types = DictType::query()
            ->where('status', Status::Enabled)
            ->with(['items' => fn (HasMany $query): HasMany => $query->where('status', Status::Enabled)->orderBy('sort')->orderBy('id')])
            ->get();

        $options = [];

        foreach ($types as $type) {
            $options[$type->code] = [];

            foreach ($type->items as $item) {
                $options[$type->code][] = ['value' => $item->value, 'label' => $item->label, 'color' => $item->color];
            }
        }

        return $options;
    }

    /**
     * 带版本号的缓存（键 CACHE_KEY）：flush() 换新版本，避免未命中回填的竞态把旧值永久写回（设计文档 §6.7）。
     */
    public static function cache(): VersionedCache
    {
        return new VersionedCache(self::CACHE_KEY);
    }
}
