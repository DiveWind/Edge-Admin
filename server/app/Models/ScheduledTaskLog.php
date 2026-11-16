<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Models\Concerns\StoresDatesInAppTimezone;
use App\Services\Settings;
use Database\Factories\ScheduledTaskLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['task', 'status', 'output', 'duration_ms', 'started_at', 'finished_at'])]
#[WithoutTimestamps]
class ScheduledTaskLog extends Model
{
    /** @use HasFactory<ScheduledTaskLogFactory> */
    use HasFactory, MassPrunable, StoresDatesInAppTimezone;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * 开始一条执行记录。
     */
    public static function start(string $task): self
    {
        return static::query()->create([
            'task' => $task,
            'status' => TaskStatus::Running,
            'started_at' => now(),
        ]);
    }

    /**
     * 结束执行记录；未给出耗时则按开始 / 结束时间计算（秒级精度）。
     */
    public function finish(TaskStatus $status, ?string $output = null, ?int $durationMs = null): void
    {
        $finishedAt = now();

        $this->update([
            'status' => $status,
            'output' => $output === null ? null : mb_substr(trim($output), 0, 10000),
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs ?? (int) $this->started_at->diffInMilliseconds($finishedAt, true),
        ]);
    }

    /**
     * 超过保留天数（系统参数 log.retention_days，默认 config('edge.logs.retention_days')）的记录由 `model:prune` 清理。
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('started_at', '<', now()->subDays(app(Settings::class)->integer('log.retention_days', config()->integer('edge.logs.retention_days'))));
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['task'] ?? null, fn (Builder $q, string $value) => $q->whereLike('task', "%{$value}%"))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value));
    }
}
