<?php

namespace App\Http\Admin\Monitor\Resources;

use App\Core\Monitor\Models\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 操作日志的输出格式（Admin 入口，设计文档 §6.6）。
 *
 * - user_id 为操作者（causer_id，用户被删除后保留原值），username 是写入时的快照；
 * - description 是路由名，前端以它为 i18n key 翻译；
 * - 列表不含 route_parameters、payload（可能很大），详情（withDetails()）含；payload 已在写入时脱敏；
 *   两者通常是对象，为空时输出为 `[]`（响应包装按数组解码 JSON，无法区分空对象与空数组）；
 * - 时间为带时区偏移的 ISO 8601。
 *
 * @mixin OperationLog
 */
class OperationLogResource extends JsonResource
{
    /**
     * 是否输出 route_parameters、payload（详情）。
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
            'id' => $this->id,
            'user_id' => $this->causer_id,
            'username' => $this->stringProperty('username'),
            'description' => $this->description,
            'method' => $this->stringProperty('method'),
            'path' => $this->stringProperty('path'),
            'status' => $this->getProperty('status'),
            'duration_ms' => $this->getProperty('duration_ms'),
            'ip_address' => $this->stringProperty('ip_address'),
            'user_agent' => $this->stringProperty('user_agent'),
            'request_id' => $this->stringProperty('request_id'),
            'route_parameters' => $this->when($this->details, fn (): mixed => $this->getProperty('route_parameters', [])),
            'payload' => $this->when($this->details, fn (): mixed => $this->getProperty('payload', [])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
