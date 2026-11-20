<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Queries\LatestHealthResults;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 最近一次健康检查的结果（GET /monitor/health，设计文档 §6.6）：`{ finished_at, checks: [...] }`，尚无结果时 finished_at 为 null。
 *
 * 没有模型：直接声明菜单节点的权限码 monitor.health，由 Gate::before 判定（§8.1）。
 */
class HealthController
{
    /**
     * @return array{finished_at: string|null, checks: list<array<string, mixed>>}
     */
    #[Authorize('monitor.health')]
    public function __invoke(LatestHealthResults $query): array
    {
        return $query->get();
    }
}
