<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class ClearLoginLogsController extends Controller
{
    /**
     * 清空登录日志。
     */
    #[Authorize('monitor.login-log.delete')]
    public function __invoke(): Response
    {
        LoginLog::query()->delete();

        return response()->noContent();
    }
}
