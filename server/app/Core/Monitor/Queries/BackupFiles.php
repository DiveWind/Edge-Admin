<?php

namespace App\Core\Monitor\Queries;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Spatie\Backup\BackupDestination\{Backup, BackupDestination};

/**
 * 备份列表（GET /monitor/backups，设计文档 §6.10）：config('backup.backup.destination.disks') 中每个盘上本应用（backup.backup.name）的备份，
 * 读取自备份盘（spatie/laravel-backup 的 BackupDestination），合并为一个列表后在内存中分页（备份数量受保留策略限制，通常几十个）。
 *
 * 每项 `{ disk, path, size, created_at }`：path 为盘内路径，size 为字节，created_at 为备份时间（带时区偏移的 ISO 8601）。
 * 无法访问的盘没有备份（健康状态见 BackupDestinations）。只读，不提供下载（§6.10）。
 */
class BackupFiles
{
    /**
     * 可排序的字段。
     *
     * @var list<string>
     */
    public const array SORTS = ['created_at', 'size'];

    /**
     * 配置的备份盘。
     *
     * @return list<string>
     */
    public static function disks(): array
    {
        $disks = config('backup.backup.destination.disks');

        return is_array($disks) ? array_values(array_filter($disks, is_string(...))) : [];
    }

    /**
     * Execute the query：按 $sort（`created_at` / `size`，`-` 前缀为降序）排序后分页；$disk 不为 null 时只列出该盘。
     *
     * @return LengthAwarePaginator<int, array{disk: string, path: string, size: int, created_at: string}>
     */
    public function paginate(?string $disk, string $sort, int $page, int $perPage): LengthAwarePaginator
    {
        $descending = str_starts_with($sort, '-');
        $field = ltrim($sort, '-');

        $backups = $this->all($disk)
            ->sortBy([[$field, $descending ? 'desc' : 'asc'], ['path', 'asc']])
            ->values();

        return new LengthAwarePaginator($backups->forPage($page, $perPage)->values(), $backups->count(), $perPage, $page);
    }

    /**
     * 全部备份。
     *
     * @return Collection<int, array{disk: string, path: string, size: int, created_at: string}>
     */
    private function all(?string $disk): Collection
    {
        $name = config()->string('backup.backup.name');

        return collect(self::disks())
            ->filter(fn (string $candidate): bool => $disk === null || $candidate === $disk)
            ->flatMap(fn (string $candidate): array => array_map(fn (Backup $backup): array => [
                'disk' => $candidate,
                'path' => $backup->path(),
                'size' => (int) $backup->sizeInBytes(),
                'created_at' => $backup->date()->toIso8601String(),
            ], BackupDestination::create($candidate, $name)->backups()->all()))
            ->values();
    }
}
