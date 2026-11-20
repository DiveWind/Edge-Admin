<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Models\{Activity, OperationLog};
use App\Core\Monitor\Services\DataMasker;
use App\Core\System\Models\User;
use Illuminate\Support\Str;

/**
 * 写入一条操作日志（activitylog 的 `operation` 日志，设计文档 §6.6）。由后台入口的 RecordOperationLog 中间件在响应发送后调用。
 *
 * - causer 为操作者，description 为路由名（前端以它为 i18n key 翻译）；properties 另存 username 快照；
 * - 脱敏在这里完成（任何调用方写入的操作日志都经过它）：payload 与 route_parameters 中键名与
 *   config('core.monitor.masked_keys') 匹配（不区分大小写，支持 * 通配，递归处理嵌套对象与数组）的值替换为 DataMasker::MASK；
 *   规则由 DataMasker 实现，与失败任务、定时任务的输出共用；
 * - 截断（同样在这里完成）：字符串值超过 config('core.monitor.operation_log.max_value_length') 个字符的，保留前面部分并加上 DataMasker::TRUNCATED；
 *   脱敏、截断后的 payload 编码为 JSON 仍超过 max_payload_bytes 字节时，整体替换为 `{ _truncated: true, _bytes: 原始请求体的 JSON 字节数 }`；
 * - user_agent 最长 Activity::USER_AGENT_LENGTH 个字符；request_id 由调用方传入（响应发送后写入时 Context 可能已清空），为 null 时由 Activity 从 Context 补上。
 *
 * 不开事务：只有一条插入。
 */
class LogOperation
{
    public function __construct(private readonly DataMasker $masker) {}

    /**
     * Execute the action.
     *
     * @param  array{method: string, path: string, route_parameters: array<array-key, mixed>, payload: array<array-key, mixed>, status: int, duration_ms: int, ip_address: string|null, user_agent: string|null, request_id: string|null}  $request
     */
    public function handle(User $operator, string $description, array $request): ?Activity
    {
        $activity = activity(OperationLog::LOG_NAME)
            ->causedBy($operator)
            ->withProperties([
                'username' => $operator->username,
                ...$request,
                'route_parameters' => $this->mask($request['route_parameters']),
                'payload' => $this->limitSize($this->mask($request['payload']), $request['payload']),
                'user_agent' => $request['user_agent'] === null ? null : Str::limit($request['user_agent'], Activity::USER_AGENT_LENGTH, ''),
            ])
            ->log($description);

        return $activity instanceof Activity ? $activity : null;
    }

    /**
     * 递归脱敏与截断：键名匹配的值整体替换为 DataMasker::MASK（值是数组时同样整体替换）；其余超过 max_value_length 的字符串值截断并加上 DataMasker::TRUNCATED。
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function mask(array $data): array
    {
        return $this->masker->maskArray($data, config()->integer('core.monitor.operation_log.max_value_length'));
    }

    /**
     * 整体上限：$payload（已脱敏、截断）编码为 JSON 超过 max_payload_bytes 时，替换为截断标记与原始请求体 $original 的 JSON 字节数。
     *
     * @param  array<array-key, mixed>  $payload
     * @param  array<array-key, mixed>  $original
     * @return array<array-key, mixed>
     */
    private function limitSize(array $payload, array $original): array
    {
        $limit = config()->integer('core.monitor.operation_log.max_payload_bytes');

        if (strlen((string) json_encode($payload, JSON_UNESCAPED_UNICODE)) <= $limit) {
            return $payload;
        }

        return ['_truncated' => true, '_bytes' => strlen((string) json_encode($original, JSON_UNESCAPED_UNICODE))];
    }
}
