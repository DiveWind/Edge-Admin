<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Services\SystemInfo;
use Illuminate\Http\JsonResponse;

class SystemInfoController extends Controller
{
    public function __invoke(SystemInfo $systemInfo): JsonResponse
    {
        return response()->json(['data' => $systemInfo->summary()]);
    }
}
