<?php

namespace App\Core\Monitor\Queries;

use Illuminate\Support\Collection;
use Spatie\Backup\Config\MonitoredBackupsConfig;
use Spatie\Backup\Tasks\Monitor\{BackupDestinationStatus, BackupDestinationStatusFactory, HealthCheckFailure};
use Spatie\Backup\Tasks\Monitor\HealthChecks\IsReachable;

/**
 * 各备份盘的健康状态（GET /monitor/backups/destinations 与健康检查 Backups，设计文档 §6.10）：按 config('backup.monitor_backups')
 * 逐个盘检查，与 `backup:monitor` 同一套规则（包的 BackupDestinationStatusFactory：可访问、最新备份的时间、总量）。
 *
 * 每项 `{ disk, name, reachable, healthy, failures: [{ check, message }], newest_backup_at, backups_count, used_storage }`：
 * - check 为包的健康检查类名（IsReachable、MaximumAgeInDays、MaximumStorageInMegabytes），message 是包的英文文案，前端按 check 翻译；
 * - 盘无法访问时 reachable 为 false，消息为固定文案，不输出连接异常的原文（可能含主机、桶名等）；
 * - used_storage 为字节；无法访问时 newest_backup_at 为 null、backups_count 与 used_storage 为 0。
 *
 * 每次调用都实时读取备份盘（按当时的时间判断），不缓存。
 */
class BackupDestinations
{
    /**
     * 无法访问时的消息。
     */
    public const string UNREACHABLE_MESSAGE = 'The backup destination cannot be reached.';

    /**
     * Execute the query.
     *
     * @return list<array{disk: string, name: string, reachable: bool, healthy: bool, failures: list<array{check: string, message: string}>, newest_backup_at: string|null, backups_count: int, used_storage: int}>
     */
    public function get(): array
    {
        return array_values($this->statuses()->map(function (BackupDestinationStatus $status): array {
            $healthy = $status->isHealthy();
            $destination = $status->backupDestination();
            $failures = $status->getHealthCheckFailures()->map(fn (HealthCheckFailure $failure): array => [
                'check' => class_basename($failure->healthCheck()),
                'message' => $failure->healthCheck() instanceof IsReachable ? self::UNREACHABLE_MESSAGE : $failure->exception()->getMessage(),
            ])->all();
            $reachable = $status->getHealthCheckFailures()->doesntContain(fn (HealthCheckFailure $failure): bool => $failure->healthCheck() instanceof IsReachable);

            return [
                'disk' => $destination->diskName(),
                'name' => $destination->backupName(),
                'reachable' => $reachable,
                'healthy' => $healthy,
                'failures' => array_values($failures),
                'newest_backup_at' => $reachable ? $destination->newestBackup()?->date()->toIso8601String() : null,
                'backups_count' => $reachable ? $destination->backups()->count() : 0,
                'used_storage' => $reachable ? (int) $destination->usedStorage() : 0,
            ];
        })->all());
    }

    /**
     * 按 monitor_backups 构造的各盘状态。
     *
     * @return Collection<int, BackupDestinationStatus>
     */
    private function statuses(): Collection
    {
        $config = config('backup.monitor_backups');

        return BackupDestinationStatusFactory::createForMonitorConfig(MonitoredBackupsConfig::fromArray(is_array($config) ? $config : []));
    }
}
