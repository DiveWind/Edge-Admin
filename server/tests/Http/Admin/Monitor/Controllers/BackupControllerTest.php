<?php

use App\Core\Monitor\Jobs\ExecuteScheduledTask;
use App\Core\Monitor\Models\ScheduledTask;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\{Bus, Route, Storage};

/*
|--------------------------------------------------------------------------
| 备份：/monitor/backups 的列表、各备份盘的健康状态、立即备份（设计文档 §6.10）
|--------------------------------------------------------------------------
|
| 备份盘用 Storage::fake()，只放空的 zip 文件，不实际执行备份。立即备份只断言派发了调度中的 backup:run（Bus::fake()）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    config(['backup.backup.name' => 'app', 'backup.backup.destination.disks' => ['backups', 'offsite']]);
    config(['backup.monitor_backups.0.name' => 'app', 'backup.monitor_backups.0.disks' => ['backups', 'offsite']]);
    Storage::fake('backups');
    Storage::fake('offsite');
});

/**
 * 在 $disk 上放一个 $at 时间的备份（文件名即备份时间，laravel-backup 的格式）。
 */
function putBackup(string $disk, string $at, int $bytes = 10): string
{
    $path = 'app/'.CarbonImmutable::parse($at)->format('Y-m-d-H-i-s').'.zip';
    Storage::disk($disk)->put($path, str_repeat('x', $bytes));

    return $path;
}

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/backups')->assertUnauthorized();
    });

    it('returns 403 without monitor.backup', function (): void {
        $this->actingAs(userWithPermissions('monitor.health'))->getJson('/api/admin/monitor/backups')->assertForbidden();
    });

    it('lists the backups of every destination, newest first, paginated', function (): void {
        putBackup('backups', '2026-11-17 01:30:00', 5);
        $newest = putBackup('backups', '2026-11-19 01:30:00', 7);
        putBackup('offsite', '2026-11-18 01:30:00', 3);
        Storage::disk('backups')->put('other-app/2026-11-19-02-00-00.zip', 'x');

        $this->actingAs(userWithPermissions('monitor.backup'))
            ->getJson('/api/admin/monitor/backups?pageSize=2')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.items', [
                ['disk' => 'backups', 'path' => $newest, 'size' => 7, 'created_at' => CarbonImmutable::parse('2026-11-19 01:30:00')->toIso8601String()],
                ['disk' => 'offsite', 'path' => 'app/2026-11-18-01-30-00.zip', 'size' => 3, 'created_at' => CarbonImmutable::parse('2026-11-18 01:30:00')->toIso8601String()],
            ]);
    });

    it('filters by disk and sorts by size', function (): void {
        putBackup('backups', '2026-11-17 01:30:00', 9);
        putBackup('backups', '2026-11-18 01:30:00', 2);
        putBackup('offsite', '2026-11-18 01:30:00', 1);

        $response = $this->actingAs(userWithPermissions('monitor.backup'))
            ->getJson('/api/admin/monitor/backups?filter[disk]=backups&sort=size')
            ->assertOk()
            ->assertJsonPath('data.total', 2);

        expect(array_column($response->json('data.items'), 'size'))->toBe([2, 9]);
    });

    it('rejects filters and sorts outside of the whitelist', function (string $query, string $error): void {
        $this->actingAs(userWithPermissions('monitor.backup'))
            ->getJson("/api/admin/monitor/backups?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($error);
    })->with([
        'unknown disk' => ['filter[disk]=local', 'filter.disk'],
        'unknown filter' => ['filter[path]=app', 'filter'],
        'unknown sort' => ['sort=path', 'sort'],
    ]);
});

describe('destinations', function (): void {
    it('returns 401 for guests and 403 without monitor.backup', function (): void {
        $this->getJson('/api/admin/monitor/backups/destinations')->assertUnauthorized();
        $this->actingAs(userWithPermissions('monitor.health'))->getJson('/api/admin/monitor/backups/destinations')->assertForbidden();
    });

    it('reports the health of every destination with the backup:monitor rules', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-19 08:00:00'));
        putBackup('backups', '2026-11-19 01:30:00', 4);
        putBackup('backups', '2026-11-18 01:30:00', 6);
        putBackup('offsite', '2026-11-10 01:30:00');

        $this->actingAs(userWithPermissions('monitor.backup'))
            ->getJson('/api/admin/monitor/backups/destinations')
            ->assertOk()
            ->assertJsonPath('data.0', [
                'disk' => 'backups', 'name' => 'app', 'reachable' => true, 'healthy' => true, 'failures' => [],
                'newest_backup_at' => CarbonImmutable::parse('2026-11-19 01:30:00')->toIso8601String(), 'backups_count' => 2, 'used_storage' => 10,
            ])
            ->assertJsonPath('data.1.disk', 'offsite')
            ->assertJsonPath('data.1.healthy', false)
            ->assertJsonPath('data.1.failures.0.check', 'MaximumAgeInDays');
    });

    it('does not reveal the connection error of an unreachable destination', function (): void {
        config(['backup.monitor_backups.0.disks' => ['missing-disk']]);

        $this->actingAs(userWithPermissions('monitor.backup'))
            ->getJson('/api/admin/monitor/backups/destinations')
            ->assertOk()
            ->assertJsonPath('data.0.reachable', false)
            ->assertJsonPath('data.0.healthy', false)
            ->assertJsonPath('data.0.failures', [['check' => 'IsReachable', 'message' => 'The backup destination cannot be reached.']])
            ->assertJsonPath('data.0.backups_count', 0);
    });
});

describe('run', function (): void {
    it('returns 401 for guests and 403 without monitor.backup.run', function (): void {
        Bus::fake();

        $this->postJson('/api/admin/monitor/backups/run')->assertUnauthorized();
        $this->actingAs(userWithPermissions('monitor.backup', 'monitor.schedule'))->postJson('/api/admin/monitor/backups/run')->assertForbidden();

        Bus::assertNothingDispatched();
    });

    it('dispatches the backup:run task of the schedule and returns at once', function (): void {
        Bus::fake();
        ScheduledTask::factory()->create(['name' => 'backup:run']);

        $this->actingAs(userWithPermissions('monitor.backup.run'))
            ->postJson('/api/admin/monitor/backups/run', [])
            ->assertOk()
            ->assertJsonPath('data', null);

        Bus::assertDispatched(ExecuteScheduledTask::class, fn (ExecuteScheduledTask $job): bool => $job->name === 'backup:run');
    });

    it('only ever runs backup:run, whatever is submitted', function (): void {
        Bus::fake();
        ScheduledTask::factory()->create(['name' => 'backup:run']);
        ScheduledTask::factory()->create(['name' => 'activitylog:clean --force']);

        $this->actingAs(userWithPermissions('monitor.backup.run'))
            ->postJson('/api/admin/monitor/backups/run', ['command' => 'db:wipe', 'name' => 'activitylog:clean --force'])
            ->assertOk();

        Bus::assertDispatchedTimes(ExecuteScheduledTask::class, 1);
        Bus::assertDispatched(ExecuteScheduledTask::class, fn (ExecuteScheduledTask $job): bool => $job->name === 'backup:run');
    });

    it('returns 422 when the backup task is not synced', function (): void {
        Bus::fake();
        ScheduledTask::factory()->create(['name' => 'backup:clean']);

        $this->actingAs(userWithPermissions('monitor.backup.run'))
            ->postJson('/api/admin/monitor/backups/run')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The backup task is not in the schedule. Enable the backup schedule and sync the scheduled tasks first.');

        Bus::assertNothingDispatched();
    });

    it('throttles with the ops limiter', function (): void {
        Bus::fake();
        config(['core.apps.admin.ops_rate_limit' => 1]);
        ScheduledTask::factory()->create(['name' => 'backup:run']);
        $actor = userWithPermissions('monitor.backup.run');

        $this->actingAs($actor)->postJson('/api/admin/monitor/backups/run')->assertOk();
        $this->actingAs($actor)->postJson('/api/admin/monitor/backups/run')->assertTooManyRequests();
    });
});

it('offers no way to download a backup', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_contains($route->uri(), 'backups'))
        ->map(fn (RouteDefinition $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($routes)->toBe([
        'GET|HEAD api/admin/monitor/backups/destinations',
        'POST api/admin/monitor/backups/run',
        'GET|HEAD api/admin/monitor/backups',
    ]);
});
