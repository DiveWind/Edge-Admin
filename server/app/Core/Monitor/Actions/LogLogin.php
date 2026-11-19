<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Enums\{LoginEvent, LoginFailureReason};
use App\Core\Monitor\Models\{Activity, LoginLog};
use App\Core\System\Models\User;
use Illuminate\Support\Str;

/**
 * 写入一条登录日志（activitylog 的 `login` 日志，设计文档 §6.6、§7.3）。由 RecordLoginActivity 监听认证事件后调用。
 *
 * - event 与 description 都是事件的值（login、logout、failed、lockout），前端翻译；
 * - causer 为事件涉及的账号（没有时为空，如用户名不存在的登录失败）；
 * - username：有账号时为账号的用户名，否则为提交的用户名（转小写、最长 USERNAME_LENGTH）；
 * - reason 只有 failed 有；via_remember 只有 login 有（是否由“记住我”的 Cookie 自动恢复登录）；
 *   同时记下 IP、User-Agent（请求 ID 由 Activity 自动补上）。
 *
 * 只接收用户名，不接收密码、登录凭据与重置密码的 token：它们在任何情况下都不会写入日志。
 */
class LogLogin
{
    /**
     * 提交的用户名的最大长度。
     */
    public const int USERNAME_LENGTH = 255;

    /**
     * Execute the action.
     */
    public function handle(
        LoginEvent $event,
        ?User $account,
        ?string $submittedUsername,
        ?LoginFailureReason $reason,
        ?string $ipAddress,
        ?string $userAgent,
        bool $viaRemember = false,
    ): ?Activity {
        $username = $account instanceof User
            ? $account->username
            : ($submittedUsername === null ? null : Str::limit(Str::lower(trim($submittedUsername)), self::USERNAME_LENGTH, ''));

        $logger = activity(LoginLog::LOG_NAME)->event($event->value);
        $logger = $account instanceof User ? $logger->causedBy($account) : $logger->causedByAnonymous();

        $activity = $logger
            ->withProperties([
                'username' => $username,
                'reason' => $event === LoginEvent::Failed ? $reason?->value : null,
                ...($event === LoginEvent::Login ? ['via_remember' => $viaRemember] : []),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent === null ? null : Str::limit($userAgent, LogOperation::USER_AGENT_LENGTH, ''),
            ])
            ->log($event->value);

        return $activity instanceof Activity ? $activity : null;
    }
}
