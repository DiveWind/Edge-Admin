<?php

namespace App\Http\Admin\Monitor\Resources;

use App\Core\Monitor\Models\ScheduledTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 定时任务的输出格式（Admin 入口，设计文档 §6.6）。
 *
 * - name 是 schedule-monitor 的任务名（命令及参数、任务类名）；timezone 未单独设置时为 null（按应用时区执行）；
 * - next_run_at 按 cron 表达式与时区计算，is_running 由上次开始、结束、失败的时间推断；
 * - 时间为带时区偏移的 ISO 8601，没有时为 null。
 *
 * @mixin ScheduledTask
 */
class ScheduledTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'cron_expression' => $this->cron_expression,
            'timezone' => $this->scheduleTimezone(),
            'last_started_at' => $this->last_started_at?->toIso8601String(),
            'last_finished_at' => $this->last_finished_at?->toIso8601String(),
            'last_failed_at' => $this->last_failed_at?->toIso8601String(),
            'last_skipped_at' => $this->last_skipped_at?->toIso8601String(),
            'next_run_at' => $this->nextRunAt()?->toIso8601String(),
            'is_running' => $this->isRunning(),
        ];
    }
}
