<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class ClearOperationLogsController extends Controller
{
    /**
     * 清空操作日志。
     */
    #[Authorize('monitor.operation-log.delete')]
    public function __invoke(): Response
    {
        OperationLog::query()->delete();

        return response()->noContent();
    }
}
