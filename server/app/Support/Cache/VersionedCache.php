<?php

namespace App\Support\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * 带版本号的整体缓存（经 Cache::memo() 读取：同一请求、队列任务内只访问一次缓存存储）。
 *
 * 数据存在 `<键>:<版本>` 下，版本存在 `<键>:version` 下（随机串，永久）；写入方在事务提交后调用 flush() 换一个新版本，旧版本的数据不再被读取。
 *
 * 解决“未命中回填”的竞态：请求 R 未命中、读库（旧值）；写入方提交并 flush()；R 之后才写回旧值——
 * 用“读后删”时旧值会一直留在缓存里，直到下一次修改；用版本号时 R 写回的是**旧版本**的键，flush() 之后的读取都用新版本的键，
 * 未命中时重新读库（已提交的新值）。版本键被淘汰时生成新版本，同样从库中重新加载。
 * 旧版本的数据带有限的 TTL（默认 1 天）自然过期，不会在 database、file 等不淘汰的存储中无限累积。
 *
 * 不保存任何状态：每次调用都取 Cache::memo()（scoped 绑定，每个请求重置），常驻内存安全。
 */
final readonly class VersionedCache
{
    /**
     * @param  string  $key  缓存键（版本键与数据键的前缀）
     * @param  int  $ttl  每个版本的数据保留的秒数
     */
    public function __construct(private string $key, private int $ttl = 86400) {}

    /**
     * 当前版本的数据；未命中时调用 $load 加载并写入。
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $load
     * @return TValue
     */
    public function remember(Closure $load): mixed
    {
        return Cache::memo()->remember($this->key.':'.$this->version(), $this->ttl, $load);
    }

    /**
     * 换一个新版本（本请求的 memo 一并更新）：之后的读取从库中重新加载。
     */
    public function flush(): void
    {
        Cache::memo()->forever($this->versionKey(), $this->newVersion());
    }

    /**
     * 当前版本；没有时生成一个。
     */
    public function version(): string
    {
        return Cache::memo()->rememberForever($this->versionKey(), $this->newVersion(...));
    }

    /**
     * 版本号的缓存键。
     */
    public function versionKey(): string
    {
        return $this->key.':version';
    }

    private function newVersion(): string
    {
        return Str::random(16);
    }
}
