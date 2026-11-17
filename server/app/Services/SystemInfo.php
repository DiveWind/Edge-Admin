<?php

namespace App\Services;

use App\Models\{Attachment, PersonalAccessToken, User};
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Throwable;

/**
 * 系统信息（GET /api/system-info）。只采集可移植获取的指标，不输出主机、账号、密钥等敏感配置；
 * 无法获取的指标（如数据库不支持统计表大小、disk_*_space 被禁用）返回 null。
 */
class SystemInfo
{
    public function __construct(
        protected Application $app,
        protected DatabaseManager $db,
        protected FailedJobProviderInterface $failer,
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

        return [
            'disk_total_bytes' => $this->diskSpace(fn (): float|false => disk_total_space($path)),
            'disk_free_bytes' => $this->diskSpace(fn (): float|false => disk_free_space($path)),
            'database_bytes' => $this->databaseSize(),
            'attachments_bytes' => (int) Attachment::query()->sum('size'),
        ];
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
        try {
            $bytes = $resolver();
        } catch (Throwable) {
            return null;
        }

        return $bytes === false ? null : (int) $bytes;
    }

    /**
     * 数据库占用：优先用 Schema::getTables() 的表大小（MySQL / PostgreSQL / 带 dbstat 的 SQLite），
     * SQLite 文件库退回文件大小。
     */
    protected function databaseSize(): ?int
    {
        $connection = $this->db->connection();

        try {
            $size = collect($connection->getSchemaBuilder()->getTables())->sum(fn (array $table): int => (int) ($table['size'] ?? 0));
        } catch (Throwable) {
            $size = 0;
        }

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
