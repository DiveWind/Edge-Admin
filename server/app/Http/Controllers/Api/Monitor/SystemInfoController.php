<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Services\SystemInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class SystemInfoController extends Controller
{
    #[Authorize('monitor.system-info.view')]
    public function __invoke(SystemInfo $systemInfo): JsonResponse
    {
        return response()->json(['data' => $systemInfo->summary()]);
    }
}
