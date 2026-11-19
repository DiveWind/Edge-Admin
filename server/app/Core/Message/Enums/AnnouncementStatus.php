<?php

namespace App\Core\Message\Enums;

use App\Core\System\Contracts\{HasColor, HasLabel};

/**
 * 公告的状态（设计文档 §6.9）：草稿 → 已发布 → 已撤回。撤回是终态（不能再发布、不能修改，只能删除）。
 *
 * - draft：可以修改、删除、发布；
 * - published：已发出站内通知，不能修改、删除（先撤回）；
 * - revoked：已撤回，发出的通知已删除；只能删除。
 *
 * 实现 HasLabel、HasColor：`/options/enums` 的 `announcement_status`（§6.7）。
 */
enum AnnouncementStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Revoked = 'revoked';

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Published => __('Published'),
            self::Revoked => __('Revoked'),
        };
    }

    /**
     * 语义色（antd Tag）：草稿 default，已发布 success，已撤回 warning。
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'default',
            self::Published => 'success',
            self::Revoked => 'warning',
        };
    }
}
