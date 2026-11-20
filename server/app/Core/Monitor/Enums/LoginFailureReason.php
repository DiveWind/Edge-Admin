<?php

namespace App\Core\Monitor\Enums;

use App\Support\Enums\HasLabel;

/**
 * 登录失败（LoginEvent::Failed）的原因（登录日志的 properties.reason，设计文档 §6.6、§7.3）。
 *
 * 实现 HasLabel：显示名称由后端统一下发给前端（设计文档 §5.5、§10.1）。
 */
enum LoginFailureReason: string implements HasLabel
{
    /** 用户名或密码错误。 */
    case InvalidCredentials = 'invalid_credentials';

    /** 账号已停用（密码正确）。 */
    case Disabled = 'disabled';

    /** 两步验证的动态码或恢复码错误。 */
    case TwoFactor = 'two_factor';

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::InvalidCredentials => __('Invalid username or password'),
            self::Disabled => __('Account disabled'),
            self::TwoFactor => __('Invalid two-factor code'),
        };
    }
}
