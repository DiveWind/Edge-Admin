<?php

namespace App\Core\Monitor\Actions;

use App\Core\Monitor\Models\FailedJob;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * 删除一条失败任务（设计文档 §6.6）：调用官方的 FailedJobProviderInterface::forget()（即 `queue:forget` 的实现）。
 * 授权由 FailedJobPolicy::delete() 判定。
 */
class ForgetFailedJob
{
    public function __construct(private readonly FailedJobProviderInterface $failer) {}

    /**
     * Execute the action.
     */
    public function handle(FailedJob $job): void
    {
        $this->failer->forget($job->uuid);
    }
}
