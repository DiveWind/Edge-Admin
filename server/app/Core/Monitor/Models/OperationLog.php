<?php

namespace App\Core\Monitor\Models;

use Database\Factories\Core\Monitor\Models\OperationLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 操作日志：activity_log 中日志名为 `operation` 的记录（多态别名 `operation_log`，见 MonitorServiceProvider；设计文档 §6.6）。
 *
 * 由后台入口的 RecordOperationLog 中间件调用 LogOperation 写入：已登录用户的写请求（排除登录、登出等认证请求）。
 * - causer：操作者；description：路由名（前端以它为 i18n key 翻译）；
 * - properties：username（写入时的快照）、method、path、route_parameters、payload（已脱敏）、status、duration_ms、
 *   ip_address、user_agent、request_id。
 *
 * 全局作用域只取 `operation` 日志：路由模型绑定、列表都取不到其他日志。只读，没有修改、删除的用例（过期日志由 activitylog:clean 清理）。
 */
class OperationLog extends Activity
{
    /** @use HasFactory<OperationLogFactory> */
    use HasFactory;

    /**
     * 日志名。
     */
    public const string LOG_NAME = 'operation';

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('log_name', fn (Builder $query) => $query->where($query->qualifyColumn('log_name'), self::LOG_NAME));
        static::creating(fn (self $log): string => $log->log_name = self::LOG_NAME);
    }
}
