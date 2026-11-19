<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Queries\SystemInfo;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 系统信息（GET /monitor/system-info，设计文档 §6.6）：只读，不输出密钥、密码、主机、路径与环境变量。
 *
 * 与健康检查同一页：直接声明菜单节点的权限码 monitor.health，由 Gate::before 判定（§8.1）。
 */
class SystemInfoController
{
    /**
     * @return array<string, mixed>
     */
    #[Authorize('monitor.health')]
    public function __invoke(SystemInfo $query): array
    {
        return $query->get();
    }
}
