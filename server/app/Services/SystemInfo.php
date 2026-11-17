<?php

namespace App\Services;

use App\Models\{Attachment, PersonalAccessToken, User};
use Illuminate\Cache\CacheManager;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * 系统信息（GET /api/system-info）。只采集可移植获取的指标，不输出主机、账号、密钥等敏感配置；
 * 无法获取的指标（如数据库不支持统计表大小、disk_*_space 被禁用）返回 null。
 *
 * 存储占用（磁盘、库大小、附件总大小）的统计开销随数据量增长（MySQL / PostgreSQL 需查询 information_schema / 系统目录），
 * 与工作台一样按 stale-while-revalidate 缓存（Cache::flexible()，见 config('edge.statistics_cache')）。
 */
class SystemInfo
{
    public const string STORAGE_CACHE_KEY = 'edge:system-info:storage';

    /**
     * @param  array{fresh: int, stale: int}  $cacheTtl
     */
    public function __construct(
        protected Application $app,
        protected DatabaseManager $db,
        protected FailedJobProviderInterface $failer,
        protected CacheManager $cache,
        #[Config('edge.statistics_cache')] protected array $cacheTtl,
    ) {}

    /**
     * @return array{app: array<string, mixed>, runtime: array<string, mixed>, drivers: array<string, mixed>, storage: array<string, mixed>, metrics: array<string, int>}
     */
    public function summary(): array
    {
        return [
            'app' => $this->application(),
            'runtime' => $this->runtime(),
            'drivers' => $this->drivers(),
            'storage' => $this->storage(),
            'metrics' => $this->metrics(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function application(): array
    {
        return [
            'name' => config()->string('app.name'),
            'env' => $this->app->environment(),
            'debug' => config()->boolean('app.debug'),
            'version' => config()->string('edge.version'),
            'locale' => config()->string('app.locale'),
            'timezone' => config()->string('app.timezone'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function runtime(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => $this->app->version(),
            'os' => PHP_OS_FAMILY.' '.php_uname('r'),
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit') ?: null,
            'upload_max_filesize' => ini_get('upload_max_filesize') ?: null,
            'post_max_size' => ini_get('post_max_size') ?: null,
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function drivers(): array
    {
        $connection = $this->db->connection();

        return [
            'database' => [
                'connection' => $connection->getName(),
                'driver' => $connection->getDriverName(),
                'version' => rescue(fn (): string => $connection->getServerVersion(), null, false),
            ],
            'cache' => config()->string('cache.default'),
            'queue' => config()->string('queue.default'),
            'session' => config()->string('session.driver'),
            'filesystem' => config()->string('filesystems.default'),
            'upload_disk' => config()->string('edge.uploads.disk'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function storage(): array
    {
        $path = storage_path();

        $compute = fn (): array => [
            'disk_total_bytes' => $this->diskSpace(fn (): float|false => disk_total_space($path)),
            'disk_free_bytes' => $this->diskSpace(fn (): float|false => disk_free_space($path)),
            'database_bytes' => $this->databaseSize(),
            'attachments_bytes' => (int) Attachment::query()->sum('size'),
        ];

        if ($this->cacheTtl['fresh'] <= 0) {
            return $compute();
        }

        return $this->cache->flexible(self::STORAGE_CACHE_KEY, [$this->cacheTtl['fresh'], max($this->cacheTtl['fresh'], $this->cacheTtl['stale'])], $compute);
    }

    /**
     * @return array<string, int>
     */
    protected function metrics(): array
    {
        return [
            'users' => User::query()->count(),
            'online_sessions' => PersonalAccessToken::query()->online()->count(),
            'attachments' => Attachment::query()->count(),
            'failed_jobs' => count($this->failer->all()),
        ];
    }

    /**
     * @param  callable(): (float|false)  $resolver
     */
    protected function diskSpace(callable $resolver): ?int
    {
        $bytes = rescue($resolver, false, false);

        return $bytes === false ? null : (int) $bytes;
    }

    /**
     * 数据库占用：优先用 Schema::getTables() 的表大小（MySQL / PostgreSQL / 带 dbstat 的 SQLite），
     * SQLite 文件库退回文件大小。
     */
    protected function databaseSize(): ?int
    {
        $connection = $this->db->connection();

        $size = rescue(fn (): int => (int) collect($connection->getSchemaBuilder()->getTables())->sum(fn (array $table): int => (int) ($table['size'] ?? 0)), 0, false);

        if ($size > 0) {
            return (int) $size;
        }

        $database = (string) $connection->getConfig('database');

        if ($connection->getDriverName() === 'sqlite' && is_file($database)) {
            return (int) filesize($database);
        }

        return null;
    }
}
