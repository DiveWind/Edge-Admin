<?php

namespace App\Http\Admin\Monitor\Resources;

use App\Core\Monitor\Models\ScheduledTaskLog;
use App\Core\Monitor\Services\DataMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 定时任务执行记录的输出格式（Admin 入口，设计文档 §6.6）：meta 展开为 runtime（秒）、exit_code、memory（字节）、failure_message、output，
 * 不适用的为 null。output 只有任务调用 storeOutputInDb() 时才有；output、failure_message 按失败任务的规则脱敏、截断（DataMasker）。
 *
 * @mixin ScheduledTaskLog
 */
class ScheduledTaskLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $output = $this->metaValue('output');
        $failure = $this->metaValue('failure_message');

        return [
            'id' => $this->id,
            'type' => $this->type,
            'runtime' => $this->metaValue('runtime'),
            'exit_code' => $this->metaValue('exit_code'),
            'memory' => $this->metaValue('memory'),
            'failure_message' => is_string($failure) ? $this->masked($failure) : null,
            'output' => is_string($output) ? $this->masked($output) : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function masked(string $output): string
    {
        $masker = resolve(DataMasker::class);

        return $masker->truncate($masker->maskText($output), config()->integer('core.monitor.failed_job.max_value_length'));
    }
}
