<?php

namespace App\Core\Monitor\Jobs;

use Exception;
use Illuminate\Console\Events\{ScheduledTaskFailed, ScheduledTaskFinished, ScheduledTaskStarting};
use Illuminate\Console\Scheduling\{Event, Schedule};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\{ShouldBeUnique, ShouldQueue};
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\{Tries, UniqueFor};
use RuntimeException;
use Spatie\ScheduleMonitor\Support\ScheduledTasks\ScheduledTaskFactory;
use Throwable;

/**
 * 立即执行一个定时任务（设计文档 §6.6）：由 RunScheduledTask 派发，只带任务名。
 *
 * - 执行的永远是调度定义（routes/console.php）中的事件：worker 是加载了调度的命令行进程（同步队列在请求中执行时，
 *   先引导命令行内核加载调度，重复调用无副作用），按与 schedule-monitor:sync 相同的规则
 *   （在当前环境运行、被监控、名称相同）在 Schedule::events() 中查找，任务名只用作查找的键；找不到时抛出异常，本任务失败；
 * - 执行过程基于 schedule:run（Laravel 13 的 ScheduleRunCommand::runEvent()）：派发 ScheduledTaskStarting → $event->run()（遵守
 *   withoutOverlapping() 的互斥锁）→ 结束事件，异常只 report()，不再抛出。schedule-monitor 监听这些事件写入执行记录，
 *   手动执行与定时执行在同一份记录中；
 * - **与 runEvent() 的一处不同**：runEvent() 在退出码非 0 时先派发 ScheduledTaskFinished，再抛出异常派发 ScheduledTaskFailed；
 *   schedule-monitor 把前者也记为 failed（markAsFinished() 见到非 0 退出码转为 markAsFailed()），于是一次执行留下两条 failed
 *   （一条带 exit_code / runtime / output，一条只有 failure_message）。这里退出码非 0 时只派发 ScheduledTaskFinished
 *   （保留退出码、耗时与输出），不再派发 ScheduledTaskFailed；抛出异常时只派发 ScheduledTaskFailed。
 *   因此手动执行一次只有“starting + 一条 finished 或 failed”。监听 ScheduledTaskFailed 的代码收不到手动执行的非 0 退出码；
 *   由 schedule:run 定时执行时仍是框架的行为（两条 failed）；
 * - 与 schedule:test 相同，在前台执行（忽略 runInBackground()，执行后恢复），不检查 when() / skip()、onOneServer() 与调度的暂停状态；
 * - 只执行一次（Tries(1)）；同一任务在排队或执行期间不重复入队（ShouldBeUnique，按任务名加锁，最长 UniqueFor 秒）。
 */
#[Tries(1)]
#[UniqueFor(3600)]
class ExecuteScheduledTask implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $name  schedule-monitor 的任务名（monitored_scheduled_tasks.name）
     */
    public function __construct(public string $name) {}

    /**
     * 唯一锁的键：同一任务同时只有一个排队或执行中的立即执行。
     */
    public function uniqueId(): string
    {
        return $this->name;
    }

    /**
     * Execute the job.
     *
     * @throws RuntimeException 调度中没有该任务
     */
    public function handle(Kernel $console, Schedule $schedule, Dispatcher $events, Container $container, ExceptionHandler $handler): void
    {
        $console->bootstrap();

        $event = $this->findEvent($schedule);
        $runInBackground = $event->runInBackground;
        $event->runInBackground = false;

        $events->dispatch(new ScheduledTaskStarting($event));

        $start = microtime(true);

        try {
            $this->runEvent($event, $events, $container, $handler, $start);
        } finally {
            $event->runInBackground = $runInBackground;
        }
    }

    /**
     * 执行事件并派发结束事件：抛出异常 → 只派发 ScheduledTaskFailed；正常返回 → 只派发 ScheduledTaskFinished
     * （退出码非 0 时由 schedule-monitor 记为失败，异常照常 report()）。一次执行只留下“开始 + 一条结束或失败”的记录。
     */
    private function runEvent(Event $event, Dispatcher $events, Container $container, ExceptionHandler $handler, float $start): void
    {
        try {
            $event->run($container);
        } catch (Throwable $e) {
            $events->dispatch(new ScheduledTaskFailed($event, $e, round(microtime(true) - $start, 2)));
            $handler->report($e);

            return;
        }

        $events->dispatch(new ScheduledTaskFinished($event, round(microtime(true) - $start, 2)));

        if ($event->exitCode !== null && $event->exitCode !== 0) {
            $handler->report(new Exception("Scheduled command [{$event->command}] failed with exit code [{$event->exitCode}]."));
        }
    }

    /**
     * 调度中名称为 $this->name、在当前环境运行且被监控的事件（同名时取第一个，与 schedule-monitor:sync 一致）。
     *
     * @throws RuntimeException
     */
    private function findEvent(Schedule $schedule): Event
    {
        $environment = config()->string('app.env');

        foreach ($schedule->events() as $event) {
            if (! $event->runsInEnvironment($environment)) {
                continue;
            }

            $task = ScheduledTaskFactory::createForEvent($event);

            if ($task->shouldMonitor() && $task->name() === $this->name) {
                return $event;
            }
        }

        throw new RuntimeException("The scheduled task [{$this->name}] is not defined in the schedule.");
    }
}
