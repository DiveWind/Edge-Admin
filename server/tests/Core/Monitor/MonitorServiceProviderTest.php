<?php

use App\Core\Message\Models\Notification;
use App\Core\Monitor\Models\{Activity, FailedJob, LoginLog, OperationLog, ScheduledTask, ScheduledTaskLog, Session};
use App\Core\Monitor\MonitorServiceProvider;
use App\Core\Monitor\Policies\PersonalAccessTokenPolicy;
use App\Core\System\Models\{Attachment, Export};
use Illuminate\Console\Scheduling\{Event, Schedule};
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\{DB, Gate, Request};
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
            "model:prune --model='".Export::class."'",
        ]);
});

it('prunes the expired exports every hour', function (): void {
    expect(scheduledEvent(Export::class)->expression)->toBe('0 * * * *');
});

/**
 * 按给定的备份调度开关重新定义一遍调度（routes/console.php），返回其中的备份任务：命令 => 事件。
 *
 * @return array<string, Event>
 */
function backupEvents(bool $scheduled): array
{
    config(['core.monitor.backup.schedule' => $scheduled]);
    $schedule = new Schedule;
    Illuminate\Support\Facades\Schedule::swap($schedule);

    require base_path('routes/console.php');

    return collect($schedule->events())
        ->filter(fn (Event $event): bool => str_contains((string) $event->command, 'backup:'))
        ->mapWithKeys(fn (Event $event): array => [(string) str((string) $event->command)->after("'artisan' ") => $event])
        ->all();
}

it('cleans, runs and monitors the backups every day when the backup schedule is on', function (): void {
    $events = backupEvents(true);

    expect(array_map(fn (Event $event): string => $event->expression, $events))
        ->toBe(['backup:clean' => '0 1 * * *', 'backup:run' => '30 1 * * *', 'backup:monitor' => '0 7 * * *'])
        ->and(array_filter($events, fn (Event $event): bool => ! $event->withoutOverlapping))->toBe([]);
});

it('does not schedule the backups when the backup schedule is off', function (): void {
    expect(backupEvents(false))->toBe([]);
});

it('syncs the backup tasks to the monitored tasks when the backup schedule is on', function (): void {
    backupEvents(true);

    $this->artisan('schedule-monitor:sync')->assertSuccessful();

    expect(ScheduledTask::query()->where('name', 'like', 'backup:%')->orderBy('name')->pluck('name')->all())
        ->toBe(['backup:clean', 'backup:monitor', 'backup:run']);
});

/**
 * 在给定的环境变量下读取 config/core.php 的 monitor.backup.schedule（null 表示未设置该变量）。
 */
function backupScheduleConfig(string $appEnv, ?string $schedule): bool
{
    $variables = ['APP_ENV' => $appEnv, 'CORE_BACKUP_SCHEDULE' => $schedule];
    $original = array_map(fn (string $name): array => [Env::get($name), Request::server($name) ?? null, getenv($name)], array_combine(array_keys($variables), array_keys($variables)));

    $set = function (string $name, ?string $value): void {
        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        } else {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    };

    try {
        foreach ($variables as $name => $value) {
            $set($name, $value);
        }

        return (require base_path('config/core.php'))['monitor']['backup']['schedule'];
    } finally {
        foreach ($original as $name => [$env, $server, $getenv]) {
            $set($name, is_string($getenv) ? $getenv : (is_string($env) ? $env : (is_string($server) ? $server : null)));
        }
    }
}

it('schedules the backups by default only in production, unless CORE_BACKUP_SCHEDULE says otherwise', function (string $appEnv, ?string $schedule, bool $expected): void {
    expect(backupScheduleConfig($appEnv, $schedule))->toBe($expected);
})->with([
    'production by default' => ['production', null, true],
    'local by default' => ['local', null, false],
    'testing by default' => ['testing', null, false],
    'turned off in production' => ['production', 'false', false],
    'turned on in local' => ['local', 'true', true],
]);

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
        ->toBe(array_values(array_filter([
            'Database',
            DB::connection()->getDriverName() === 'sqlite' ? null : 'DatabaseConnectionCount',
            'Cache', 'UsedDiskSpace', 'Queue', 'Schedule', 'DebugMode', 'Environment', 'OptimizedApp',
        ])));
});

it('checks the backups only when the backup schedule is on', function (bool $scheduled): void {
    config(['core.monitor.backup.schedule' => $scheduled]);

    expect(in_array('Backups', healthCheckNames(), true))->toBe($scheduled);
})->with(['on' => [true], 'off' => [false]]);

it('checks the database connection count only on MySQL, MariaDB and PostgreSQL', function (string $driver, bool $registered): void {
    config(['database.connections.'.config('database.default').'.driver' => $driver]);

    expect(in_array('DatabaseConnectionCount', healthCheckNames(), true))->toBe($registered);
})->with([['mysql', true], ['mariadb', true], ['pgsql', true], ['sqlite', false]]);

it('can turn off the disk space check', function (): void {
    config(['core.monitor.health.disk_space.enabled' => false]);

    expect(healthCheckNames())->not->toContain('UsedDiskSpace');
});
