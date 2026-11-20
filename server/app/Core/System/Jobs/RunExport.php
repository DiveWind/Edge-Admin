<?php

namespace App\Core\System\Jobs;

use App\Core\System\Actions\{FailExport, GenerateExport};
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\{FailOnTimeout, Tries};
use Throwable;

/**
 * 生成导出文件（设计文档 §6.10）：由 CreateExport 在事务提交后派发，只带导出记录的 id（记录被删除即取消，不因找不到模型而报错）。
 *
 * - 只执行一次（Tries(1)）：导出只读、但可能很重，失败后由用户重新发起；
 * - 超时 config('core.system.export.timeout') 秒（任务级，优先于 worker 的 --timeout），超时即失败（FailOnTimeout）。
 *   必须小于队列连接的 retry_after 并留出余量，否则任务仍在执行时会被另一个 worker 再次取出（§11.3）；
 * - 任何失败（异常、超时、worker 被杀后超过尝试次数）都由 failed() 调用 FailExport：标记失败并通知发起人。
 *   异常细节只在失败任务中（§6.6），不写入导出记录。
 */
#[Tries(1)]
#[FailOnTimeout]
class RunExport implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * 任务的超时（秒）。
     */
    public int $timeout;

    public function __construct(public readonly int $exportId)
    {
        $this->timeout = config()->integer('core.system.export.timeout');
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateExport $action): void
    {
        $action->handle($this->exportId);
    }

    /**
     * 失败（框架在新的任务实例上调用）：标记导出失败。
     */
    public function failed(?Throwable $exception): void
    {
        resolve(FailExport::class)->handle($this->exportId);
    }
}
