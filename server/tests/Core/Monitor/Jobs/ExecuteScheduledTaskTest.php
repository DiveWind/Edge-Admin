<?php

use App\Core\Monitor\Jobs\ExecuteScheduledTask;
use App\Core\Monitor\Models\{ScheduledTask, ScheduledTaskLog};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| 立即执行定时任务（设计文档 §6.6）
|--------------------------------------------------------------------------
|
| 执行的永远是调度中定义的事件：任务名只用作查找的键。用进程内执行的回调任务（monitorName 命名），不启动子进程。
|
*/

/**
 * 在调度中定义一个回调任务，执行时调用 $callback。
 */
function scheduleCallback(string $name, Closure $callback): void
{
    resolve(Schedule::class)->call($callback)->daily()->monitorName($name);
}

/**
 * @return list<string>
 */
function logTypesOf(ScheduledTask $task): array
{
    return ScheduledTaskLog::query()->where('monitored_scheduled_task_id', $task->id)->orderBy('id')->pluck('type')->all();
}

it('runs the scheduled event of that name and records it like schedule:run', function (): void {
    $runs = [];
    scheduleCallback('demo:first', function () use (&$runs): void {
        $runs[] = 'first';
    });
    scheduleCallback('demo:second', function () use (&$runs): void {
        $runs[] = 'second';
    });
    $task = ScheduledTask::factory()->create(['name' => 'demo:second']);

    dispatch_sync(new ExecuteScheduledTask('demo:second'));

    expect($runs)->toBe(['second'])
        ->and(logTypesOf($task))->toBe(['starting', 'finished'])
        ->and($task->refresh()->last_started_at)->not->toBeNull()
        ->and($task->last_finished_at)->not->toBeNull();
});

it('records a failure without rethrowing it', function (): void {
    $this->mock(ExceptionHandler::class)->shouldReceive('report')->once();
    scheduleCallback('demo:broken', function (): void {
        throw new RuntimeException('The backup disk is full.');
    });
    $task = ScheduledTask::factory()->create(['name' => 'demo:broken']);

    dispatch_sync(new ExecuteScheduledTask('demo:broken'));

    $failure = ScheduledTaskLog::query()->where('type', 'failed')->sole();

    expect(logTypesOf($task))->toBe(['starting', 'failed'])
        ->and($failure->metaValue('failure_message'))->toBe('The backup disk is full.')
        ->and($task->refresh()->last_failed_at)->not->toBeNull();
});

it('refuses a name that is not defined in the schedule, so no arbitrary command can run', function (string $name): void {
    expect(fn () => dispatch_sync(new ExecuteScheduledTask($name)))
        ->toThrow(RuntimeException::class, "The scheduled task [{$name}] is not defined in the schedule.");
})->with(['db:wipe --force', 'inspire', '']);

it('does not run a task excluded from monitoring', function (): void {
    $ran = false;
    resolve(Schedule::class)->call(function () use (&$ran): void {
        $ran = true;
    })->daily()->monitorName('demo:hidden')->doNotMonitor();

    expect(fn () => dispatch_sync(new ExecuteScheduledTask('demo:hidden')))->toThrow(RuntimeException::class)
        ->and($ran)->toBeFalse();
});

it('does not run a task scheduled for another environment', function (): void {
    $ran = false;
    resolve(Schedule::class)->call(function () use (&$ran): void {
        $ran = true;
    })->daily()->environments('production')->monitorName('demo:production');

    expect(fn () => dispatch_sync(new ExecuteScheduledTask('demo:production')))->toThrow(RuntimeException::class)
        ->and($ran)->toBeFalse();
});

it('is not queued again while the same task is queued or running', function (): void {
    Queue::fake();

    dispatch(new ExecuteScheduledTask('activitylog:clean --force'));
    dispatch(new ExecuteScheduledTask('activitylog:clean --force'));
    dispatch(new ExecuteScheduledTask('model:prune'));

    Queue::assertPushed(ExecuteScheduledTask::class, 2);
});

it('records a command that exits with a non-zero code as one failure with its exit code', function (): void {
    $this->mock(ExceptionHandler::class)->shouldReceive('report')->once()
        ->withArgs(fn (Throwable $e): bool => $e->getMessage() === 'Scheduled command [exit 3] failed with exit code [3].');
    resolve(Schedule::class)->exec('exit 3')->daily();
    $task = ScheduledTask::factory()->create(['name' => 'exit 3', 'type' => 'shell']);

    dispatch_sync(new ExecuteScheduledTask('exit 3'));

    $failure = ScheduledTaskLog::query()->where('type', 'failed')->sole();

    expect(logTypesOf($task))->toBe(['starting', 'failed'])
        ->and($failure->metaValue('exit_code'))->toBe(3)
        ->and($task->refresh()->last_failed_at)->not->toBeNull()
        ->and($task->last_finished_at)->toBeNull();
});

it('records a command that exits with zero as finished', function (): void {
    resolve(Schedule::class)->exec('exit 0')->daily();
    $task = ScheduledTask::factory()->create(['name' => 'exit 0', 'type' => 'shell']);

    dispatch_sync(new ExecuteScheduledTask('exit 0'));

    expect(logTypesOf($task))->toBe(['starting', 'finished'])
        ->and(ScheduledTaskLog::query()->where('type', 'finished')->sole()->metaValue('exit_code'))->toBe(0)
        ->and($task->refresh()->last_failed_at)->toBeNull();
});
