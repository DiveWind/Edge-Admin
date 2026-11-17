<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Actions\Monitor\QueueScheduledTask;
use App\Http\Controllers\Controller;
use App\Services\ScheduledTaskRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class RunScheduledTaskController extends Controller
{
    /**
     * 立即执行一次（入队）。
     */
    #[Authorize('monitor.scheduled-task.run')]
    public function __invoke(string $key, ScheduledTaskRegistry $registry, QueueScheduledTask $queueScheduledTask): JsonResponse
    {
        $task = $registry->find($key) ?? abort(404);

        $queueScheduledTask->handle($task);

        return response()->json(['message' => __('messages.task_queued')], 202);
    }
}
