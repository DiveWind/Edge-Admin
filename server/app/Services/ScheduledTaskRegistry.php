<?php

namespace App\Services;

use App\Models\ScheduledTaskLog;
use Illuminate\Console\Application;
use Illuminate\Console\Scheduling\{Event, Schedule};
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\{Collection, Str};

/**
 * 读取 routes/console.php 中定义的 Artisan 命令任务。
 */
class ScheduledTaskRegistry
{
    public function __construct(
        protected Schedule $schedule,
        protected ConsoleKernel $console,
    ) {}

    /**
     * @return Collection<int, ScheduledTask>
     */
    public function all(): Collection
    {
        // HTTP 请求中 routes/console.php 默认不会加载，引导 Console Kernel 以注册调度事件（幂等）。
        $this->console->bootstrap();

        $tasks = collect($this->schedule->events())
            ->filter(fn (Event $event): bool => str_contains((string) $event->command, Application::artisanBinary()))
            ->map(fn (Event $event): ScheduledTask => new ScheduledTask(
                key: self::keyFor(self::commandFor($event)),
                command: self::commandFor($event),
                description: $event->description,
                expression: $event->expression,
                nextRunAt: $event->nextRunDate(),
            ))
            ->values();

        $lastLogs = ScheduledTaskLog::query()
            ->whereIn('id', ScheduledTaskLog::query()
                ->selectRaw('max(id)')
                ->whereIn('task', $tasks->pluck('command'))
                ->groupBy('task'))
            ->get()
            ->keyBy('task');

        return $tasks->map(fn (ScheduledTask $task): ScheduledTask => $task->withLastLog($lastLogs->get($task->command)));
    }

    public function find(string $key): ?ScheduledTask
    {
        return $this->all()->first(fn (ScheduledTask $task): bool => $task->key === $key);
    }

    /**
     * 去掉 PHP / artisan 可执行路径后的命令，如 `sanctum:prune-expired --hours=24`。
     */
    public static function commandFor(Event $event): string
    {
        return trim(Str::after((string) $event->command, Application::artisanBinary()));
    }

    /**
     * 稳定的任务标识：命令的 slug，如 `sanctum-prune-expired-hours-24`。
     */
    public static function keyFor(string $command): string
    {
        return Str::slug((string) preg_replace('/[^A-Za-z0-9]+/', '-', $command));
    }
}
