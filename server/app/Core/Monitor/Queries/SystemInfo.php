<?php

namespace App\Core\Monitor\Queries;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * 系统信息（GET /monitor/system-info，设计文档 §6.6）：运行环境、各驱动与磁盘占用。每次请求实时读取，开销很小，不缓存。
 *
 * **不输出**密钥、密码、用户名、主机名与端口、APP_URL、任何路径、环境变量：只输出版本号、开关、驱动与连接 / 存储的名称。
 * 取不到的值（函数被禁用、数据库不提供版本）为 null。
 */
class SystemInfo
{
    public function __construct(
        private readonly Application $app,
        private readonly DatabaseManager $db,
    ) {}

    /**
     * Execute the query.
     *
     * @return array{app: array<string, mixed>, runtime: array<string, mixed>, drivers: array<string, mixed>, disk: array<string, int|null>, server_time: string}
     */
    public function get(): array
    {
        return [
            'app' => [
                'name' => config()->string('app.name'),
                'environment' => $this->app->environment(),
                'debug' => config()->boolean('app.debug'),
                'maintenance' => $this->app->isDownForMaintenance(),
                'timezone' => config()->string('app.timezone'),
                'locale' => config()->string('app.locale'),
                'fallback_locale' => config()->string('app.fallback_locale'),
            ],
            'runtime' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => $this->app->version(),
                'server' => $this->server(),
                'sapi' => PHP_SAPI,
                'os' => PHP_OS_FAMILY,
                'opcache' => $this->opcacheEnabled(),
                'memory_limit' => $this->ini('memory_limit'),
                'max_execution_time' => $this->ini('max_execution_time'),
                'upload_max_filesize' => $this->ini('upload_max_filesize'),
                'post_max_size' => $this->ini('post_max_size'),
            ],
            'drivers' => $this->drivers(),
            'disk' => $this->disk(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * 运行方式：Octane worker、PHP-FPM、命令行，其他为 PHP_SAPI 原值。
     */
    private function server(): string
    {
        if (isset($_SERVER['LARAVEL_OCTANE'])) {
            return 'octane';
        }

        return match (PHP_SAPI) {
            'fpm-fcgi' => 'fpm',
            'cli' => 'cli',
            default => PHP_SAPI,
        };
    }

    /**
     * 本进程是否启用了 OPcache（命令行下通常为 false）。
     */
    private function opcacheEnabled(): bool
    {
        if (! function_exists('opcache_get_status')) {
            return false;
        }

        $status = @opcache_get_status(false);

        return is_array($status) && ($status['opcache_enabled'] ?? false) === true;
    }

    private function ini(string $key): ?string
    {
        $value = ini_get($key);

        return $value === false ? null : $value;
    }

    /**
     * 各驱动：只输出连接 / 存储的名称与驱动名。
     *
     * @return array<string, array<string, string|null>>
     */
    private function drivers(): array
    {
        $connection = $this->db->connection();
        $cache = config()->string('cache.default');
        $queue = config()->string('queue.default');
        $disk = config()->string('filesystems.default');

        return [
            'database' => [
                'connection' => $connection->getName(),
                'driver' => $connection->getDriverName(),
                'version' => $this->databaseVersion(),
            ],
            'cache' => ['store' => $cache, 'driver' => $this->stringConfig("cache.stores.{$cache}.driver")],
            'queue' => ['connection' => $queue, 'driver' => $this->stringConfig("queue.connections.{$queue}.driver")],
            'session' => ['driver' => $this->stringConfig('session.driver')],
            'filesystem' => ['disk' => $disk, 'driver' => $this->stringConfig("filesystems.disks.{$disk}.driver")],
        ];
    }

    private function databaseVersion(): ?string
    {
        try {
            $version = $this->db->connection()->getServerVersion();
        } catch (Throwable) {
            return null;
        }

        return $version === '' ? null : $version;
    }

    /**
     * storage/ 所在磁盘的容量（字节）；只输出数值，不输出路径。
     *
     * @return array{total_bytes: int|null, free_bytes: int|null, used_bytes: int|null}
     */
    private function disk(): array
    {
        $path = storage_path();
        $total = function_exists('disk_total_space') ? @disk_total_space($path) : false;
        $free = function_exists('disk_free_space') ? @disk_free_space($path) : false;

        $total = $total === false ? null : (int) $total;
        $free = $free === false ? null : (int) $free;

        return [
            'total_bytes' => $total,
            'free_bytes' => $free,
            'used_bytes' => $total === null || $free === null ? null : $total - $free,
        ];
    }

    private function stringConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) ? $value : null;
    }
}
