<?php

namespace App\Http\Admin\Monitor\Resources;

use App\Core\Monitor\Models\FailedJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 失败任务的输出格式（Admin 入口，设计文档 §6.6）。
 *
 * - id 是 uuid（路由参数同此）；name 是 payload 的 displayName；exception 是异常的第一行；
 * - 详情（withDetails()）另含 payload 摘要（去掉序列化的任务对象）与 trace（调用栈，限制行数）；
 * - 输出前已脱敏、去掉项目根目录（FailedJob 的 payloadSummary()、exceptionMessage()、exceptionTrace()，规则同操作日志）；
 * - failed_at 为带时区偏移的 ISO 8601。
 *
 * @mixin FailedJob
 */
class FailedJobResource extends JsonResource
{
    /**
     * 是否输出 payload、trace（详情）。
     */
    private bool $details = false;

    /**
     * 输出详情字段。
     */
    public function withDetails(): static
    {
        $this->details = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'name' => $this->displayName(),
            'exception' => $this->exceptionMessage(),
            'payload' => $this->when($this->details, fn (): array => $this->payloadSummary()),
            'trace' => $this->when($this->details, fn (): string => $this->exceptionTrace()),
            'failed_at' => $this->failed_at->toIso8601String(),
        ];
    }
}
