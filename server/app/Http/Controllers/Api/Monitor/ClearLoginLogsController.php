<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\Response;

class ClearLoginLogsController extends Controller
{
    /**
     * 清空登录日志。
     */
    public function __invoke(): Response
    {
        LoginLog::query()->delete();

        return response()->noContent();
    }
}
