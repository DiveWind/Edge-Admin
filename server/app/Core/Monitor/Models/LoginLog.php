<?php

namespace App\Core\Monitor\Models;

use App\Core\Monitor\Enums\{LoginEvent, LoginFailureReason};
use Database\Factories\Core\Monitor\Models\LoginLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 登录日志：activity_log 中日志名为 `login` 的记录（多态别名 `login_log`，见 MonitorServiceProvider；设计文档 §6.6、§7.3）。
 *
 * 由 RecordLoginActivity 监听认证事件后调用 LogLogin 写入。
 * - event：LoginEvent（login、logout、failed、lockout、password_reset）；description 同 event；
 * - causer：事件涉及的账号（用户名不存在时为空）；
 * - properties：username（成功时为账号的用户名，失败时为提交的用户名）、reason（LoginFailureReason，只有 failed 有）、
 *   via_remember（只有 login 有）、
 *   ip_address、user_agent、request_id。**不记录密码。**
 *
 * 全局作用域只取 `login` 日志。只读，过期日志由 activitylog:clean 清理。
 */
class LoginLog extends Activity
{
    /** @use HasFactory<LoginLogFactory> */
    use HasFactory;

    /**
     * 日志名。
     */
    public const string LOG_NAME = 'login';

    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('log_name', fn (Builder $query) => $query->where($query->qualifyColumn('log_name'), self::LOG_NAME));
        static::creating(fn (self $log): string => $log->log_name = self::LOG_NAME);
    }

    /**
     * 事件（库中的值不是已知事件时为 null）。
     */
    public function loginEvent(): ?LoginEvent
    {
        return is_string($this->event) ? LoginEvent::tryFrom($this->event) : null;
    }

    /**
     * 失败原因（只有 failed 有）。
     */
    public function failureReason(): ?LoginFailureReason
    {
        $reason = $this->stringProperty('reason');

        return $reason === null ? null : LoginFailureReason::tryFrom($reason);
    }
}
