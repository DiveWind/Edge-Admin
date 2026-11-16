<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatistics;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(DashboardStatistics $statistics): JsonResponse
    {
        return response()->json(['data' => $statistics->summary()]);
    }
}
