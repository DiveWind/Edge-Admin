<?php

namespace App\Core\Monitor\Checks;

use App\Core\Monitor\Queries\BackupDestinations;
use Spatie\Health\Checks\{Check, Result};

/**
 * 健康检查 `Backups`（设计文档 §6.6、§6.10）：每个备份盘都健康——与 `backup:monitor`、备份页的健康状态同一套规则
 * （BackupDestinations，读取 config('backup.monitor_backups')：可访问、最新备份不超过 BACKUP_MAX_AGE_DAYS 天、总量不超过 BACKUP_MAX_STORAGE_MB）。
 *
 * - 全部健康：ok；任一盘不健康：failed，short_summary 为不健康的盘（逗号分隔），notification_message 为第一条失败原因；
 * - meta：`{ destinations: [{ disk, healthy, newest_backup_at, backups_count }] }`。
 *
 * 不用 laravel-health 自带的 BackupsCheck：它的时间阈值在注册（应用启动）时计算，常驻内存下会停在 worker 启动的时刻，
 * 且与 monitor_backups 重复定义一套标准。本检查在 run() 时按当时的时间判断；只在备份调度开启时注册（MonitorServiceProvider）。
 */
class BackupsCheck extends Check
{
    /**
     * 执行检查。
     */
    public function run(): Result
    {
        $destinations = resolve(BackupDestinations::class)->get();
        $unhealthy = array_values(array_filter($destinations, fn (array $destination): bool => ! $destination['healthy']));

        $result = Result::make()->meta(['destinations' => array_map(fn (array $destination): array => [
            'disk' => $destination['disk'],
            'healthy' => $destination['healthy'],
            'newest_backup_at' => $destination['newest_backup_at'],
            'backups_count' => $destination['backups_count'],
        ], $destinations)]);

        if ($destinations === []) {
            return $result->failed('No backup destinations are monitored.');
        }

        if ($unhealthy === []) {
            return $result->shortSummary(count($destinations).' healthy')->ok();
        }

        $first = $unhealthy[0]['failures'][0]['message'] ?? 'The backups are unhealthy.';

        return $result->shortSummary(implode(', ', array_column($unhealthy, 'disk')))->failed($first);
    }
}
