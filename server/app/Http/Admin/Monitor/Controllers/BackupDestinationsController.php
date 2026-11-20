<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Queries\BackupDestinations;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 各备份盘的健康状态（GET /monitor/backups/destinations，设计文档 §6.10）：数组（不分页，§6.3 的例外），规则与 backup:monitor 相同。
 *
 * 没有模型：直接声明菜单节点的权限码 monitor.backup，由 Gate::before 判定（§8.1）。
 */
class BackupDestinationsController
{
    /**
     * @return list<array<string, mixed>>
     */
    #[Authorize('monitor.backup')]
    public function __invoke(BackupDestinations $query): array
    {
        return $query->get();
    }
}
