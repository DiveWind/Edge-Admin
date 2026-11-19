<?php

use App\Core\Monitor\Models\FailedJob;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Support\Facades\{Event, Queue};

/*
|--------------------------------------------------------------------------
| 失败任务：/monitor/failed-jobs 的列表、详情、重试、删除与清空（设计文档 §6.6）
|--------------------------------------------------------------------------
|
| 失败任务驱动与默认配置相同（database-uuids）。重试走官方的 queue:retry（派发 JobRetryRequested、推回原队列），
| 删除、清空走 FailedJobProviderInterface。脱敏、截断的完整规则见 tests/Core/Monitor/Models/FailedJobTest.php。
|
*/

beforeEach(function (): void {
    config(['queue.failed.driver' => 'database-uuids']);
    syncBuiltinPermissions();
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/failed-jobs')->assertUnauthorized();
    });

    it('returns 403 without the monitor.failed-job code', function (): void {
        $this->actingAs(userWithPermissions('monitor.schedule'))
            ->getJson('/api/admin/monitor/failed-jobs')
            ->assertForbidden();
    });

    it('lists the failed jobs, latest first, without the payload and trace', function (): void {
        $older = FailedJob::factory()->create(['failed_at' => now()->subHour()]);
        $latest = FailedJob::factory()->create(['queue' => 'mail']);

        $response = $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson('/api/admin/monitor/failed-jobs')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.id', [$latest->uuid, $older->uuid])
            ->assertJsonPath('data.items.0', [
                'id' => $latest->uuid,
                'connection' => 'database',
                'queue' => 'mail',
                'name' => 'App\\Jobs\\SendReport',
                'exception' => 'RuntimeException: The report service is down. in app/Jobs/SendReport.php:42',
                'failed_at' => now()->startOfSecond()->toIso8601String(),
            ]);

        expect($response->getContent())->not->toContain(base_path());
    });

    it('filters by the queue, connection, exception keyword and time range', function (string $filter): void {
        $match = FailedJob::factory()->create([
            'queue' => 'mail', 'connection' => 'redis', 'exception' => 'Swift_TransportException: Connection refused', 'failed_at' => '2026-11-19 08:00:00',
        ]);
        FailedJob::factory()->create(['failed_at' => '2026-11-18 08:00:00']);

        $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson("/api/admin/monitor/failed-jobs?{$filter}")
            ->assertJsonPath('data.items.*.id', [$match->uuid]);
    })->with([
        'filter[queue]=mail',
        'filter[connection]=redis',
        'filter[exception]=refused',
        'filter[failed_at]=2026-11-19T00:00:00Z,',
    ]);

    it('rejects unknown filters, sorts and invalid ranges with 422', function (string $query): void {
        $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson("/api/admin/monitor/failed-jobs?{$query}")
            ->assertUnprocessable();
    })->with(['filter[payload]=x', 'sort=queue', 'filter[failed_at]=yesterday']);

    it('lists nothing when the failed job driver is not database-uuids', function (): void {
        FailedJob::factory()->create();
        config(['queue.failed.driver' => 'file']);

        $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson('/api/admin/monitor/failed-jobs')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => ['items' => [], 'total' => 0], 'message' => 'ok']);
    });
});

describe('show', function (): void {
    it('returns 401 for guests', function (): void {
        $job = FailedJob::factory()->create();

        $this->getJson("/api/admin/monitor/failed-jobs/{$job->uuid}")->assertUnauthorized();
    });

    it('returns 403 without the monitor.failed-job code', function (): void {
        $job = FailedJob::factory()->create();

        $this->actingAs(userWithPermissions('monitor.health'))
            ->getJson("/api/admin/monitor/failed-jobs/{$job->uuid}")
            ->assertForbidden();
    });

    it('returns 404 for an unknown or malformed id', function (string $id): void {
        $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson("/api/admin/monitor/failed-jobs/{$id}")
            ->assertNotFound();
    })->with(['9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d', '1', 'not-a-uuid']);

    it('shows the masked payload summary and the trace without the serialized job or the project path', function (): void {
        $job = FailedJob::factory()->create([
            'payload' => json_encode([
                'displayName' => 'App\\Jobs\\SyncAccount',
                'maxTries' => 3,
                'data' => ['commandName' => 'App\\Jobs\\SyncAccount', 'command' => 'O:20:"App\\Jobs\\SyncAccount":1:{s:8:"password";s:6:"hunter";}'],
                'meta' => ['api_token' => 'tok-123', 'account' => 'acme'],
            ]),
        ]);

        $response = $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson("/api/admin/monitor/failed-jobs/{$job->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $job->uuid)
            ->assertJsonPath('data.name', 'App\\Jobs\\SyncAccount')
            ->assertJsonPath('data.payload', [
                'displayName' => 'App\\Jobs\\SyncAccount',
                'maxTries' => 3,
                'data' => ['commandName' => 'App\\Jobs\\SyncAccount'],
                'meta' => ['api_token' => '******', 'account' => 'acme'],
            ])
            ->assertJsonPath('data.trace', "Stack trace:\n#0 vendor/laravel/framework/src/Illuminate/Container/BoundMethod.php(36): App\\Jobs\\SendReport->handle()\n#1 {main}");

        expect($response->getContent())->not->toContain('hunter', 'tok-123', base_path());
    });

    it('returns 404 when the failed job driver is not database-uuids', function (): void {
        $job = FailedJob::factory()->create();
        config(['queue.failed.driver' => 'file']);

        $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->getJson("/api/admin/monitor/failed-jobs/{$job->uuid}")
            ->assertNotFound();
    });
});

describe('retry', function (): void {
    it('returns 401 for guests', function (): void {
        $job = FailedJob::factory()->create();

        $this->postJson("/api/admin/monitor/failed-jobs/{$job->uuid}/retry")->assertUnauthorized();
    });

    it('returns 403 without the monitor.failed-job.retry code', function (): void {
        $job = FailedJob::factory()->create();

        $this->actingAs(userWithPermissions('monitor.failed-job.delete'))
            ->postJson("/api/admin/monitor/failed-jobs/{$job->uuid}/retry")
            ->assertForbidden();

        expect(FailedJob::query()->count())->toBe(1);
    });

    it('returns 404 for an unknown job', function (): void {
        $this->actingAs(userWithPermissions('monitor.failed-job.retry'))
            ->postJson('/api/admin/monitor/failed-jobs/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d/retry')
            ->assertNotFound();
    });

    it('pushes the job back onto its queue through queue:retry and removes the record', function (): void {
        Queue::fake();
        Event::fake([JobRetryRequested::class]);
        $job = FailedJob::factory()->create(['queue' => 'reports']);
        $other = FailedJob::factory()->create();

        $this->actingAs(userWithPermissions('monitor.failed-job.retry'))
            ->postJson("/api/admin/monitor/failed-jobs/{$job->uuid}/retry")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        Event::assertDispatched(JobRetryRequested::class, fn (JobRetryRequested $event): bool => $event->job->id === $job->uuid);
        expect(Queue::rawPushes())->toHaveCount(1)
            ->and(Queue::rawPushes()[0]['queue'])->toBe('reports')
            ->and(FailedJob::query()->pluck('uuid')->all())->toBe([$other->uuid]);
    });

    it('is rate limited', function (): void {
        Queue::fake();
        config(['core.apps.admin.ops_rate_limit' => 1]);
        $actor = userWithPermissions('monitor.failed-job.retry');
        [$first, $second] = FailedJob::factory()->count(2)->create();

        $this->actingAs($actor)->postJson("/api/admin/monitor/failed-jobs/{$first->uuid}/retry")->assertOk();
        $this->actingAs($actor)->postJson("/api/admin/monitor/failed-jobs/{$second->uuid}/retry")->assertTooManyRequests();
        $this->actingAs($actor)->postJson('/api/admin/monitor/failed-jobs/retry')->assertTooManyRequests();
    });
});

describe('retry all', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/monitor/failed-jobs/retry')->assertUnauthorized();
    });

    it('returns 403 without the monitor.failed-job.retry code', function (): void {
        FailedJob::factory()->create();

        $this->actingAs(userWithPermissions('monitor.failed-job'))
            ->postJson('/api/admin/monitor/failed-jobs/retry')
            ->assertForbidden();

        expect(FailedJob::query()->count())->toBe(1);
    });

    it('rejects an invalid queue and unknown fields with 422', function (array $body): void {
        $this->actingAs(userWithPermissions('monitor.failed-job.retry'))
            ->postJson('/api/admin/monitor/failed-jobs/retry', $body)
            ->assertUnprocessable();
    })->with([[['queue' => ['mail']]], [['ids' => ['all']]]]);

    it('retries every failed job through queue:retry all', function (): void {
        Queue::fake();
        Event::fake([JobRetryRequested::class]);
        FailedJob::factory()->count(2)->create();

        $this->actingAs(userWithPermissions('monitor.failed-job.retry'))
            ->postJson('/api/admin/monitor/failed-jobs/retry')
            ->assertOk();

        Event::assertDispatchedTimes(JobRetryRequested::class, 2);
        expect(Queue::rawPushes())->toHaveCount(2)
            ->and(FailedJob::query()->count())->toBe(0);
    });

    it('retries only the jobs of the given queue', function (): void {
        Queue::fake();
        $mail = FailedJob::factory()->create(['queue' => 'mail']);
        $default = FailedJob::factory()->create();

        $this->actingAs(userWithPermissions('monitor.failed-job.retry'))
            ->postJson('/api/admin/monitor/failed-jobs/retry', ['queue' => 'mail'])
            ->assertOk();

        expect(Queue::rawPushes())->toHaveCount(1)
            ->and(FailedJob::query()->pluck('uuid')->all())->toBe([$default->uuid])
            ->and(FailedJob::query()->find($mail->id))->toBeNull();
    });
});

describe('destroy', function (): void {
    it('returns 401 for guests', function (): void {
        $job = FailedJob::factory()->create();

        $this->deleteJson("/api/admin/monitor/failed-jobs/{$job->uuid}")->assertUnauthorized();
    });

    it('returns 403 without the monitor.failed-job.delete code', function (): void {
        $job = FailedJob::factory()->create();

        $this->actingAs(userWithPermissions('monitor.failed-job.retry'))
            ->deleteJson("/api/admin/monitor/failed-jobs/{$job->uuid}")
            ->assertForbidden();

        expect(FailedJob::query()->count())->toBe(1);
    });

    it('returns 404 for an unknown job', function (): void {
        $this->actingAs(userWithPermissions('monitor.failed-job.delete'))
            ->deleteJson('/api/admin/monitor/failed-jobs/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d')
            ->assertNotFound();
    });

    it('forgets only that job without retrying it', function (): void {
        Queue::fake();
        $job = FailedJob::factory()->create();
        $other = FailedJob::factory()->create();

        $this->actingAs(userWithPermissions('monitor.failed-job.delete'))
            ->deleteJson("/api/admin/monitor/failed-jobs/{$job->uuid}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        Queue::assertNothingPushed();
        expect(FailedJob::query()->pluck('uuid')->all())->toBe([$other->uuid]);
    });
});

describe('flush', function (): void {
    it('returns 401 for guests', function (): void {
        $this->deleteJson('/api/admin/monitor/failed-jobs')->assertUnauthorized();
    });

    it('returns 403 without the monitor.failed-job.delete code', function (): void {
        FailedJob::factory()->create();

        $this->actingAs(userWithPermissions('monitor.failed-job.retry'))
            ->deleteJson('/api/admin/monitor/failed-jobs')
            ->assertForbidden();

        expect(FailedJob::query()->count())->toBe(1);
    });

    it('deletes every failed job', function (): void {
        FailedJob::factory()->count(3)->create();

        $this->actingAs(userWithPermissions('monitor.failed-job.delete'))
            ->deleteJson('/api/admin/monitor/failed-jobs')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        expect(FailedJob::query()->count())->toBe(0);
    });
});
