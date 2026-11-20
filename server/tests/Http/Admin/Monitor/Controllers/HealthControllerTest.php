<?php

use Illuminate\Support\Facades\{Cache, DB};
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Result;
use Spatie\Health\ResultStores\ResultStore;

/*
|--------------------------------------------------------------------------
| 健康检查与系统信息：/monitor/health、/monitor/health/check、/monitor/system-info（设计文档 §6.6）
|--------------------------------------------------------------------------
|
| 检查项为 MonitorServiceProvider 注册的默认检查（测试库为 SQLite，不注册数据库连接数）；结果存在缓存中（测试为 array）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

/**
 * 健康检查结果中每一项的结构。
 */
const HEALTH_CHECK_KEYS = ['name', 'label', 'status', 'short_summary', 'notification_message', 'meta'];

describe('health', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/health')->assertUnauthorized();
    });

    it('returns 403 without the monitor.health code', function (): void {
        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson('/api/admin/monitor/health')
            ->assertForbidden();
    });

    it('returns no results before the first check', function (): void {
        $this->actingAs(userWithPermissions('monitor.health'))
            ->getJson('/api/admin/monitor/health')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => ['finished_at' => null, 'checks' => []], 'message' => 'ok']);
    });

    it('returns the results stored by the scheduled health:check', function (): void {
        $this->travelTo(now()->startOfSecond());
        $this->artisan('health:check', ['--no-notification' => true])->assertSuccessful();

        $response = $this->actingAs(userWithPermissions('monitor.health'))
            ->getJson('/api/admin/monitor/health')
            ->assertOk()
            ->assertJsonPath('data.finished_at', now()->toIso8601String())
            ->assertJsonStructure(['data' => ['finished_at', 'checks' => ['*' => HEALTH_CHECK_KEYS]]]);

        $checks = collect($response->json('data.checks'))->keyBy('name');

        expect($checks->keys()->all())->toEqualCanonicalizing(array_values(array_filter([
            'Database', DB::connection()->getDriverName() === 'sqlite' ? null : 'DatabaseConnectionCount',
            'Cache', 'UsedDiskSpace', 'Queue', 'Schedule', 'DebugMode', 'Environment', 'OptimizedApp',
        ])))
            ->and($checks['Database']['status'])->toBe('ok')
            ->and($checks['Environment']['status'])->toBe('failed')
            ->and($checks['Environment']['meta'])->toBe(['actual' => 'testing', 'expected' => 'production'])
            ->and($checks['Schedule']['status'])->toBe('failed');
    });

    it('masks the notification messages', function (): void {
        $result = Result::make()->failed('Could not connect: password=hunter2')->check(ScheduleCheck::new());
        resolve(ResultStore::class)->save(collect([$result->endedAt(now())]));

        $response = $this->actingAs(userWithPermissions('monitor.health'))->getJson('/api/admin/monitor/health')->assertOk();

        expect($response->json('data.checks.0.notification_message'))->toBe('Could not connect: password=******');
    });
});

describe('check', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/monitor/health/check')->assertUnauthorized();
    });

    it('returns 403 without the monitor.health.check code', function (): void {
        $this->actingAs(userWithPermissions('monitor.health'))
            ->postJson('/api/admin/monitor/health/check')
            ->assertForbidden();
    });

    it('runs every check now, stores and returns the results', function (): void {
        Cache::put(resolve(ScheduleCheck::class)->getCacheKey(), now()->getTimestamp());
        $actor = userWithPermissions('monitor.health.check');

        $response = $this->actingAs($actor)
            ->postJson('/api/admin/monitor/health/check')
            ->assertOk()
            ->assertJsonStructure(['data' => ['finished_at', 'checks' => ['*' => HEALTH_CHECK_KEYS]]]);

        expect(collect($response->json('data.checks'))->firstWhere('name', 'Schedule')['status'])->toBe('ok');

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/health')
            ->assertJsonPath('data', $response->json('data'));
    });

    it('is rate limited', function (): void {
        config(['core.apps.admin.ops_rate_limit' => 1]);
        $actor = userWithPermissions('monitor.health.check');

        $this->actingAs($actor)->postJson('/api/admin/monitor/health/check')->assertOk();
        $this->actingAs($actor)->postJson('/api/admin/monitor/health/check')->assertTooManyRequests();
    });
});

describe('system info', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/system-info')->assertUnauthorized();
    });

    it('returns 403 without the monitor.health code', function (): void {
        $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson('/api/admin/monitor/system-info')
            ->assertForbidden();
    });

    it('describes the runtime and drivers', function (): void {
        $this->actingAs(userWithPermissions('monitor.health'))
            ->getJson('/api/admin/monitor/system-info')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'app' => ['name', 'environment', 'debug', 'maintenance', 'timezone', 'locale', 'fallback_locale'],
                'runtime' => ['php_version', 'laravel_version', 'server', 'sapi', 'os', 'opcache', 'memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size'],
                'drivers' => [
                    'database' => ['connection', 'driver', 'version'], 'cache' => ['store', 'driver'], 'queue' => ['connection', 'driver'],
                    'session' => ['driver'], 'filesystem' => ['disk', 'driver'],
                ],
                'disk' => ['total_bytes', 'free_bytes', 'used_bytes'],
                'server_time',
            ]])
            ->assertJsonPath('data.app.environment', 'testing')
            ->assertJsonPath('data.app.maintenance', false)
            ->assertJsonPath('data.runtime.php_version', PHP_VERSION)
            ->assertJsonPath('data.runtime.laravel_version', app()->version())
            ->assertJsonPath('data.runtime.server', 'cli')
            ->assertJsonPath('data.drivers.database', ['connection' => config('database.default'), 'driver' => DB::connection()->getDriverName(), 'version' => DB::connection()->getServerVersion()])
            ->assertJsonPath('data.drivers.cache', ['store' => 'array', 'driver' => 'array'])
            ->assertJsonPath('data.drivers.queue', ['connection' => 'sync', 'driver' => 'sync']);
    });

    it('does not expose keys, passwords, hosts, paths or environment variables', function (): void {
        config([
            'app.key' => 'base64:c2VjcmV0LWFwcC1rZXktZm9yLXRlc3Rpbmc=',
            'app.url' => 'https://internal-admin.example.test',
            'database.connections.sqlite.password' => 'db-password-123',
            'database.connections.sqlite.host' => 'db.internal.example.test',
            'mail.mailers.smtp.password' => 'mail-password-456',
        ]);
        putenv('CORE_TEST_SECRET=env-secret-789');

        $content = (string) $this->actingAs(userWithPermissions('monitor.health'))
            ->getJson('/api/admin/monitor/system-info')
            ->assertOk()
            ->getContent();

        putenv('CORE_TEST_SECRET');

        expect($content)->not->toContain(
            'c2VjcmV0LWFwcC1rZXktZm9yLXRlc3Rpbmc',
            'internal-admin.example.test',
            'db-password-123',
            'db.internal.example.test',
            'mail-password-456',
            'env-secret-789',
            str_replace('/', '\\/', base_path()),
            base_path(),
        );
    });
});
