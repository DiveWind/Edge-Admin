<?php

namespace App\Core\System\Enums;

use App\Core\System\Contracts\{HasColor, HasLabel};

/**
 * 通用的启用 / 停用状态（设计文档 §6.4）：用户、角色、菜单节点共用，接口中为字符串 `enabled` / `disabled`。
 *
 * - 用户：停用的账号不能登录，已登录的会话与 token 立即失效（§7.3）；
 * - 角色：停用的角色，其权限与数据范围都不生效（§8.4、§8.5）；
 * - 菜单节点：停用向子孙级联，停用的节点及其子孙对所有人（含超管）失效（§8.2）。
 *
 * 实现 HasLabel、HasColor：显示名称与语义色由后端统一下发给前端（设计文档 §5.5、§10.1）。
 */
enum Status: string implements HasColor, HasLabel
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

    /**
     * 语义色（antd Tag）：启用 success，停用 error。
     */
    public function color(): string
    {
        return match ($this) {
            self::Enabled => 'success',
            self::Disabled => 'error',
        };
    }
}
