<?php

namespace App\Core\Message\Enums;

use App\Core\System\Contracts\HasLabel;

/**
 * 公告的目标范围（设计文档 §6.9）：全部用户，或拥有任一目标角色（启用的角色）的用户。只发给启用的用户。
 *
 * 实现 HasLabel：`/options/enums` 的 `announcement_target`（§6.7）。
 */
enum AnnouncementTarget: string implements HasLabel
{
    case All = 'all';
    case Roles = 'roles';

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::All => __('All users'),
            self::Roles => __('Specified roles'),
        };
    }
}
