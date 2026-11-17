<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduledTaskResource;
use App\Services\ScheduledTaskRegistry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class ScheduledTaskController extends Controller
{
    public function __construct(protected ScheduledTaskRegistry $registry) {}

    /**
     * routes/console.php 中定义的定时任务。
     */
    #[Authorize('monitor.scheduled-task.view')]
    public function index(): AnonymousResourceCollection
    {
        return ScheduledTaskResource::collection($this->registry->all());
    }
}
