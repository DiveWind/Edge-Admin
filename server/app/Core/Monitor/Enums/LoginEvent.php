<?php

namespace App\Core\Monitor\Enums;

use App\Core\System\Contracts\{HasColor, HasLabel};

/**
 * 登录日志的事件（activity_log 的 event 列，设计文档 §6.6、§7.3）。“结果”即事件：成功为 login，失败为 failed、lockout；logout、password_reset 是成功的认证操作。
 *
 * 实现 HasLabel、HasColor：显示名称与语义色由后端统一下发给前端（设计文档 §5.5、§10.1）。
 */
enum LoginEvent: string implements HasColor, HasLabel
{
    /** 登录成功（Illuminate\Auth\Events\Login；开启两步验证时在验证通过后）。 */
    case Login = 'login';

    /** 登出（Illuminate\Auth\Events\Logout）。 */
    case Logout = 'logout';

    /** 登录失败（Illuminate\Auth\Events\Failed、Fortify 的 TwoFactorAuthenticationFailed），原因见 LoginFailureReason。 */
    case Failed = 'failed';

    /** 登录过于频繁，被限流拒绝（Illuminate\Auth\Events\Lockout）。 */
    case Lockout = 'lockout';

    /** 通过找回密码重置了密码（Illuminate\Auth\Events\PasswordReset，Fortify 的 POST /reset-password）。 */
    case PasswordReset = 'password_reset';

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Login => __('Signed in'),
            self::Logout => __('Signed out'),
            self::Failed => __('Sign-in failed'),
            self::Lockout => __('Too many sign-in attempts'),
            self::PasswordReset => __('Password reset'),
        };
    }

    /**
     * 语义色（antd Tag）：成功 success，失败 error，被限流 warning，登出 default，重置密码 processing。
     */
    public function color(): string
    {
        return match ($this) {
            self::Login => 'success',
            self::Logout => 'default',
            self::Failed => 'error',
            self::Lockout => 'warning',
            self::PasswordReset => 'processing',
        };
    }
}
