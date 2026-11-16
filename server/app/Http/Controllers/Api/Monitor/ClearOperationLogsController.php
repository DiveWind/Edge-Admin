<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use Illuminate\Http\Response;

class ClearOperationLogsController extends Controller
{
    /**
     * 清空操作日志。
     */
    public function __invoke(): Response
    {
        OperationLog::query()->delete();

        return response()->noContent();
    }
}
