<?php

namespace App\Core\Message\Enums;

use App\Core\System\Contracts\{HasColor, HasLabel};

/**
 * 通知与公告的级别（设计文档 §6.9）：接口中为字符串 `info` / `success` / `warning` / `error`，前端据此显示图标与颜色。
 *
 * 实现 HasLabel、HasColor：显示名称与语义色由后端统一下发给前端（`/options/enums` 的 `notification_level`，§6.7）。
 */
enum NotificationLevel: string implements HasColor, HasLabel
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Info => __('Info'),
            self::Success => __('Success'),
            self::Warning => __('Warning'),
            self::Error => __('Error'),
        };
    }

    /**
     * 语义色（antd Tag）。
     */
    public function color(): string
    {
        return match ($this) {
            self::Info => 'processing',
            self::Success => 'success',
            self::Warning => 'warning',
            self::Error => 'error',
        };
    }
}
