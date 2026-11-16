<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduledTaskResource;
use App\Jobs\RunScheduledTask;
use App\Services\ScheduledTaskRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduledTaskController extends Controller
{
    public function __construct(protected ScheduledTaskRegistry $registry) {}

    /**
     * routes/console.php 中定义的定时任务。
     */
    public function index(): AnonymousResourceCollection
    {
        return ScheduledTaskResource::collection($this->registry->all());
    }

    /**
     * 立即执行一次（入队）。
     */
    public function run(string $key): JsonResponse
    {
        $task = $this->registry->find($key) ?? abort(404);

        RunScheduledTask::dispatch($task->command);

        return response()->json(['message' => __('messages.task_queued')], 202);
    }
}
