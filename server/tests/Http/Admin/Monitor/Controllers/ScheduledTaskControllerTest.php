<?php

use App\Core\Monitor\Jobs\ExecuteScheduledTask;
use App\Core\Monitor\Models\{ScheduledTask, ScheduledTaskLog};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| 定时任务：/monitor/schedules 的列表、执行记录与立即执行（设计文档 §6.6）
|--------------------------------------------------------------------------
|
| 任务由 schedule-monitor:sync 同步（这里用工厂直接写入）；立即执行的查找与执行规则见 tests/Core/Monitor/Jobs/ExecuteScheduledTaskTest.php。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/schedules')->assertUnauthorized();
    });

    it('returns 403 without the monitor.schedule code', function (): void {
        $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson('/api/admin/monitor/schedules')
            ->assertForbidden();
    });

    it('lists the synced tasks with their last runs, next run and running state', function (): void {
        $this->travelTo(now()->setDateTime(2026, 11, 19, 10, 30));
        $running = ScheduledTask::factory()->running()->create(['name' => 'b:running', 'cron_expression' => '*/5 * * * *']);
        $idle = ScheduledTask::factory()->finished()->create(['name' => 'a:idle', 'cron_expression' => '0 3 * * *', 'timezone' => 'Asia/Shanghai']);

        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson('/api/admin/monitor/schedules')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.name', ['a:idle', 'b:running'])
            ->assertJsonPath('data.items.0', [
                'id' => $idle->id,
                'name' => 'a:idle',
                'type' => 'command',
                'cron_expression' => '0 3 * * *',
                'timezone' => 'Asia/Shanghai',
                'last_started_at' => now()->subMinutes(2)->toIso8601String(),
                'last_finished_at' => now()->subMinute()->toIso8601String(),
                'last_failed_at' => null,
                'last_skipped_at' => null,
                'next_run_at' => '2026-11-19T19:00:00+00:00',
                'is_running' => false,
            ])
            ->assertJsonPath('data.items.1.timezone', null)
            ->assertJsonPath('data.items.1.next_run_at', '2026-11-19T10:35:00+00:00')
            ->assertJsonPath('data.items.1.is_running', true)
            ->assertJsonPath('data.items.1.id', $running->id);
    });

    it('treats a task whose last run failed after starting as not running', function (): void {
        ScheduledTask::factory()->create(['last_started_at' => now()->subMinutes(2), 'last_failed_at' => now()->subMinute()]);

        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson('/api/admin/monitor/schedules')
            ->assertJsonPath('data.items.0.is_running', false);
    });

    it('filters by the name and type', function (string $filter): void {
        $match = ScheduledTask::factory()->create(['name' => 'backup:run', 'type' => 'shell']);
        ScheduledTask::factory()->create(['name' => 'activitylog:clean --force']);

        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson("/api/admin/monitor/schedules?{$filter}")
            ->assertJsonPath('data.items.*.id', [$match->id]);
    })->with(['filter[name]=backup', 'filter[type]=shell']);

    it('rejects unknown filters, sorts and invalid values with 422', function (string $query): void {
        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson("/api/admin/monitor/schedules?{$query}")
            ->assertUnprocessable();
    })->with(['filter[cron_expression]=x', 'sort=cron_expression', 'filter[type]=closure']);
});

describe('logs', function (): void {
    it('returns 401 for guests', function (): void {
        $task = ScheduledTask::factory()->create();

        $this->getJson("/api/admin/monitor/schedules/{$task->id}/logs")->assertUnauthorized();
    });

    it('returns 403 without the monitor.schedule code', function (): void {
        $task = ScheduledTask::factory()->create();

        $this->actingAs(userWithPermissions('monitor.health'))
            ->getJson("/api/admin/monitor/schedules/{$task->id}/logs")
            ->assertForbidden();
    });

    it('returns 404 for a missing task', function (): void {
        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson('/api/admin/monitor/schedules/999/logs')
            ->assertNotFound();
    });

    it('lists only the logs of the task, latest first, with the meta expanded', function (): void {
        $task = ScheduledTask::factory()->create();
        $starting = ScheduledTaskLog::factory()->for($task, 'monitoredScheduledTask')->starting()->create();
        $finished = ScheduledTaskLog::factory()->for($task, 'monitoredScheduledTask')->create();
        $failed = ScheduledTaskLog::factory()->for($task, 'monitoredScheduledTask')->failed('Disk is full.')->create();
        ScheduledTaskLog::factory()->create();

        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson("/api/admin/monitor/schedules/{$task->id}/logs")
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.items.*.id', [$failed->id, $finished->id, $starting->id])
            ->assertJsonPath('data.items.0.failure_message', 'Disk is full.')
            ->assertJsonPath('data.items.1', [
                'id' => $finished->id,
                'type' => 'finished',
                'runtime' => 0.25,
                'exit_code' => 0,
                'memory' => 2097152,
                'failure_message' => null,
                'output' => null,
                'created_at' => $finished->created_at?->toIso8601String(),
            ]);
    });

    it('masks and truncates the stored output', function (): void {
        config(['core.monitor.failed_job.max_value_length' => 30]);
        $task = ScheduledTask::factory()->create();
        ScheduledTaskLog::factory()->for($task, 'monitoredScheduledTask')->create([
            'meta' => ['exit_code' => 0, 'output' => 'Connecting with password=hunter2 to the backup server'],
        ]);

        $response = $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson("/api/admin/monitor/schedules/{$task->id}/logs")
            ->assertJsonPath('data.items.0.output', 'Connecting with password=*****…(truncated)');

        expect($response->getContent())->not->toContain('hunter2');
    });

    it('filters by the types and the time range', function (): void {
        $task = ScheduledTask::factory()->create();
        $failed = ScheduledTaskLog::factory()->for($task, 'monitoredScheduledTask')->failed()->create(['created_at' => '2026-11-19 08:00:00']);
        $finished = ScheduledTaskLog::factory()->for($task, 'monitoredScheduledTask')->create(['created_at' => '2026-11-19 09:00:00']);
        ScheduledTaskLog::factory()->for($task, 'monitoredScheduledTask')->starting()->create(['created_at' => '2026-11-19 09:00:00']);
        $actor = userWithPermissions('monitor.schedule');

        $this->actingAs($actor)
            ->getJson("/api/admin/monitor/schedules/{$task->id}/logs?filter[type]=failed,finished")
            ->assertJsonPath('data.items.*.id', [$finished->id, $failed->id]);

        $this->actingAs($actor)
            ->getJson("/api/admin/monitor/schedules/{$task->id}/logs?filter[type]=failed&filter[created_at]=2026-11-19T07:30:00Z,2026-11-19T08:30:00Z")
            ->assertJsonPath('data.items.*.id', [$failed->id]);
    });

    it('rejects invalid filters with 422', function (string $query): void {
        $task = ScheduledTask::factory()->create();

        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson("/api/admin/monitor/schedules/{$task->id}/logs?{$query}")
            ->assertUnprocessable();
    })->with(['filter[type]=done', 'filter[created_at]=yesterday', 'filter[meta]=x', 'sort=type']);
});

describe('run', function (): void {
    it('returns 401 for guests', function (): void {
        $task = ScheduledTask::factory()->create();

        $this->postJson("/api/admin/monitor/schedules/{$task->id}/run")->assertUnauthorized();
    });

    it('returns 403 without the monitor.schedule.run code', function (): void {
        Queue::fake();
        $task = ScheduledTask::factory()->create();

        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->postJson("/api/admin/monitor/schedules/{$task->id}/run")
            ->assertForbidden();

        Queue::assertNothingPushed();
    });

    it('returns 404 for a task that has not been synced, without accepting any command', function (): void {
        Queue::fake();

        $this->actingAs(userWithPermissions('monitor.schedule.run'))
            ->postJson('/api/admin/monitor/schedules/inspire/run')
            ->assertNotFound();

        Queue::assertNothingPushed();
    });

    it('queues the task by its name and returns immediately', function (): void {
        Queue::fake();
        $task = ScheduledTask::factory()->create(['name' => 'activitylog:clean --force']);

        $this->actingAs(userWithPermissions('monitor.schedule.run'))
            ->postJson("/api/admin/monitor/schedules/{$task->id}/run", ['command' => 'db:wipe --force'])
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        Queue::assertPushed(ExecuteScheduledTask::class, fn (ExecuteScheduledTask $job): bool => $job->name === 'activitylog:clean --force');
    });

    it('runs the scheduled event with the sync queue and records the run', function (): void {
        $runs = 0;
        resolve(Schedule::class)->call(function () use (&$runs): void {
            $runs++;
        })->daily()->monitorName('demo:callback');
        $task = ScheduledTask::factory()->create(['name' => 'demo:callback', 'type' => 'closure']);

        $this->actingAs(userWithPermissions('monitor.schedule.run'))
            ->postJson("/api/admin/monitor/schedules/{$task->id}/run")
            ->assertOk();

        expect($runs)->toBe(1)
            ->and($task->refresh()->last_finished_at)->not->toBeNull()
            ->and(ScheduledTaskLog::query()->where('monitored_scheduled_task_id', $task->id)->pluck('type')->all())->toBe(['starting', 'finished']);
    });

    it('is rate limited', function (): void {
        Queue::fake();
        config(['core.apps.admin.ops_rate_limit' => 2]);
        $task = ScheduledTask::factory()->create();
        $actor = userWithPermissions('monitor.schedule.run');

        $this->actingAs($actor)->postJson("/api/admin/monitor/schedules/{$task->id}/run")->assertOk();
        $this->actingAs($actor)->postJson("/api/admin/monitor/schedules/{$task->id}/run")->assertOk();
        $this->actingAs($actor)->postJson("/api/admin/monitor/schedules/{$task->id}/run")->assertTooManyRequests();
    });
});
