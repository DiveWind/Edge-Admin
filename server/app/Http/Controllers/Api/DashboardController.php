<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class DashboardController extends Controller
{
    #[Authorize('dashboard.view')]
    public function __invoke(DashboardStatistics $statistics): JsonResponse
    {
        return response()->json(['data' => $statistics->summary()]);
    }
}
