<?php

namespace App\Core\System\Enums;

use App\Core\System\Contracts\HasLabel;

/**
 * 用户的账号状态。停用的账号不能登录，已登录的会话与 token 立即失效（设计文档 §7.3）。
 *
 * 实现 HasLabel：显示名称由后端统一下发给前端（设计文档 §5.5、§10.1）。
 */
enum UserStatus: string implements HasLabel
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Enabled => __('Enabled'),
            self::Disabled => __('Disabled'),
        };
    }
}
