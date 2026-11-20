<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Actions\RunHealthChecks;
use App\Core\Monitor\Queries\LatestHealthResults;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 立即健康检查（POST /monitor/health/check，设计文档 §6.6）：同步执行全部检查（官方的 health:check），返回与 GET /monitor/health 相同的结构。
 *
 * 直接声明按钮的权限码 monitor.health.check，由 Gate::before 判定（§8.1）；路由另经过 admin-ops 限流。
 */
class CheckHealthController
{
    /**
     * @return array{finished_at: string|null, checks: list<array<string, mixed>>}
     */
    #[Authorize('monitor.health.check')]
    public function __invoke(RunHealthChecks $action, LatestHealthResults $results): array
    {
        $action->handle();

        return $results->get();
    }
}
