<?php

namespace App\Core\Monitor\Actions;

use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * 清空全部失败任务（设计文档 §6.6）：调用官方的 FailedJobProviderInterface::flush()（即 `queue:flush` 的实现）。
 * 对任何失败任务驱动都有效。授权由 FailedJobPolicy::deleteAny() 判定。
 */
class FlushFailedJobs
{
    public function __construct(private readonly FailedJobProviderInterface $failer) {}

    /**
     * Execute the action.
     */
    public function handle(): void
    {
        $this->failer->flush();
    }
}
