<?php

use App\Core\Message\Models\Notification;
use App\Core\Monitor\Models\{Activity, FailedJob, LoginLog, OperationLog, ScheduledTask, ScheduledTaskLog, Session};
use App\Core\Monitor\MonitorServiceProvider;
use App\Core\Monitor\Policies\PersonalAccessTokenPolicy;
use App\Core\System\Models\Attachment;
use Illuminate\Console\Scheduling\{Event, Schedule};
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Health\Checks\Check;
use Spatie\Health\Facades\Health;
use Spatie\ScheduleMonitor\Support\ScheduledTasks\ScheduledTasks;
use Spatie\ScheduleMonitor\Support\ScheduledTasks\Tasks\Task;

/*
|--------------------------------------------------------------------------
| Monitor 模块的注册（设计文档 §6.6）
|--------------------------------------------------------------------------
*/

it('registers the morph aliases of its models', function (): void {
    expect(Relation::getMorphedModel('activity'))->toBe(Activity::class)
        ->and(Relation::getMorphedModel('operation_log'))->toBe(OperationLog::class)
        ->and(Relation::getMorphedModel('login_log'))->toBe(LoginLog::class)
        ->and(Relation::getMorphedModel('session'))->toBe(Session::class)
        ->and(Relation::getMorphedModel('scheduled_task'))->toBe(ScheduledTask::class)
        ->and(Relation::getMorphedModel('scheduled_task_log'))->toBe(ScheduledTaskLog::class)
        ->and(Relation::getMorphedModel('failed_job'))->toBe(FailedJob::class);
});

it('binds the policy of the Sanctum tokens', function (): void {
    expect(Gate::getPolicyFor(PersonalAccessToken::class))->toBeInstanceOf(PersonalAccessTokenPolicy::class);
});

it('cleans the old logs every day', function (): void {
    $events = collect(resolve(Schedule::class)->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'activitylog:clean'));

    expect($events)->toHaveCount(1)
        ->and($events->sole()->command)->toContain('--force')
        ->and($events->sole()->expression)->toBe('0 0 * * *');
});

it('deletes the logs older than the configured days', function (): void {
    config(['activitylog.clean_after_days' => 30]);
    $old = OperationLog::factory()->create(['created_at' => now()->subDays(31)]);
    $recent = LoginLog::factory()->create(['created_at' => now()->subDays(29)]);

    $this->artisan('activitylog:clean', ['--force' => true])->assertSuccessful();

    expect(Activity::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(Activity::query()->find($old->id))->toBeNull();
});

/**
 * 调度中命令包含 $command 的事件。
 */
function scheduledEvent(string $command): Event
{
    return collect(resolve(Schedule::class)->events())
        ->sole(fn (Event $event): bool => str_contains((string) $event->command, $command));
}

it('prunes the old scheduled task logs every day', function (): void {
    expect(scheduledEvent('ScheduledTaskLog')->command)->toContain('model:prune')
        ->and(scheduledEvent('ScheduledTaskLog')->expression)->toBe('0 0 * * *');
});

it('deletes the scheduled task logs older than the configured days', function (): void {
    config(['schedule-monitor.delete_log_items_older_than_days' => 30]);
    $old = ScheduledTaskLog::factory()->create(['created_at' => now()->subDays(31)]);
    $recent = ScheduledTaskLog::factory()->create(['created_at' => now()->subDays(29)]);

    $this->artisan('model:prune', ['--model' => [ScheduledTaskLog::class]])->assertSuccessful();

    expect(ScheduledTaskLog::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(ScheduledTaskLog::query()->find($old->id))->toBeNull();
});

it('runs the health checks and heartbeats every minute without monitoring them', function (string $command): void {
    expect(scheduledEvent($command)->expression)->toBe('* * * * *');

    $monitored = ScheduledTasks::createForSchedule()->uniqueTasks()->map(fn (Task $task): ?string => $task->name());

    expect($monitored->filter(fn (?string $name): bool => str_contains((string) $name, 'health:'))->all())->toBe([]);
})->with(['health:check --no-notification', 'health:schedule-check-heartbeat', 'health:queue-check-heartbeat']);

it('monitors the other scheduled tasks and syncs them', function (): void {
    $this->artisan('schedule-monitor:sync')->assertSuccessful();

    expect(ScheduledTask::query()->orderBy('name')->pluck('name')->all())
        ->toBe([
            'activitylog:clean --force',
            "model:prune --model='".Notification::class."'",
            "model:prune --model='".ScheduledTaskLog::class."'",
            "model:prune --model='".Attachment::class."'",
        ]);
});

/**
 * MonitorServiceProvider 按当前配置给出的健康检查项的名称。
 *
 * @return list<string>
 */
function healthCheckNames(): array
{
    $provider = new class(app()) extends MonitorServiceProvider
    {
        /**
         * @return list<Check>
         */
        public function checks(): array
        {
            return $this->healthChecks();
        }
    };

    return array_map(fn (Check $check): string => $check->getName(), $provider->checks());
}

it('registers the default health checks', function (): void {
    expect(Health::registeredChecks()->map(fn (Check $check): string => $check->getName())->all())
        ->toBe(['Database', 'Cache', 'UsedDiskSpace', 'Queue', 'Schedule', 'DebugMode', 'Environment', 'OptimizedApp']);
});

it('checks the database connection count only on MySQL, MariaDB and PostgreSQL', function (string $driver, bool $registered): void {
    config(['database.connections.sqlite.driver' => $driver]);

    expect(in_array('DatabaseConnectionCount', healthCheckNames(), true))->toBe($registered);
})->with([['mysql', true], ['mariadb', true], ['pgsql', true], ['sqlite', false]]);

it('can turn off the disk space check', function (): void {
    config(['core.monitor.health.disk_space.enabled' => false]);

    expect(healthCheckNames())->not->toContain('UsedDiskSpace');
});
