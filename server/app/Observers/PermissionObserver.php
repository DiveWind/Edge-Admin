<?php

namespace App\Observers;

use App\Services\PermissionRegistry;

/**
 * 菜单权限节点变更时清除请求内的权限树缓存；绕过模型事件的批量更新需手动 PermissionRegistry::flush()。
 */
class PermissionObserver
{
    public function __construct(protected PermissionRegistry $permissions) {}

    public function saved(): void
    {
        $this->permissions->flush();
    }

    public function deleted(): void
    {
        $this->permissions->flush();
    }
}
