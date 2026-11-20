<?php

use App\Core\Monitor\Models\{LoginLog, OperationLog};
use App\Core\System\Models\User;

/*
|--------------------------------------------------------------------------
| 操作日志：/monitor/operation-logs 的列表与详情（设计文档 §6.6）
|--------------------------------------------------------------------------
|
| 日志按 causer 的数据范围过滤：userWithPermissions() 的角色选用 `self`（本人与自己创建的用户的日志）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/monitor/operation-logs')->assertUnauthorized();
    });

    it('returns 403 without the monitor.operation-log code', function (): void {
        $this->actingAs(userWithDataScope('all', 'monitor.login-log'))
            ->getJson('/api/admin/monitor/operation-logs')
            ->assertForbidden();
    });

    it('lists the operation logs without the payload, newest first', function (): void {
        $actor = userWithDataScope('all', 'monitor.operation-log');
        $first = OperationLog::factory()->causedBy($actor)->create(['created_at' => '2026-11-19 08:00:00']);
        $second = OperationLog::factory()->causedBy($actor)->create();
        LoginLog::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/operation-logs')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.id', [$second->id, $first->id])
            ->assertJsonPath('data.items.1', [
                'id' => $first->id,
                'user_id' => $actor->id,
                'username' => $actor->username,
                'description' => 'admin.system.users.store',
                'method' => 'POST',
                'path' => '/api/admin/system/users',
                'status' => 201,
                'duration_ms' => 12,
                'ip_address' => $first->getProperty('ip_address'),
                'user_agent' => $first->getProperty('user_agent'),
                'request_id' => $first->getProperty('request_id'),
                'created_at' => '2026-11-19T08:00:00+00:00',
            ]);
    });

    it('only lists the logs of users within the data scope', function (): void {
        $actor = userWithPermissions('monitor.operation-log');
        $own = OperationLog::factory()->causedBy($actor)->create();
        $created = OperationLog::factory()->causedBy(User::factory()->create(['created_by' => $actor->id]))->create();
        OperationLog::factory()->causedBy(User::factory()->create())->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/monitor/operation-logs')
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$created->id, $own->id]);
    });

    it('filters the logs', function (string $filter, string $value, array $properties): void {
        $actor = userWithDataScope('all', 'monitor.operation-log');
        $match = OperationLog::factory()->causedBy($actor)->withProperties($properties)->create(['description' => 'admin.system.roles.update']);
        OperationLog::factory()->causedBy(User::factory()->create(['username' => 'bob']))->create();

        $this->actingAs($actor)
            ->getJson("/api/admin/monitor/operation-logs?filter[{$filter}]=".urlencode($value))
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$match->id]);
    })->with([
        'method' => ['method', 'PUT', ['method' => 'PUT']],
        'status' => ['status', '422', ['status' => 422]],
        'request id' => ['request_id', 'request-0001', ['request_id' => 'request-0001']],
        'username' => ['username', 'ALI', ['username' => 'alice']],
        'description' => ['description', 'roles', []],
        'ip address' => ['ip_address', '10.9.', ['ip_address' => '10.9.8.7']],
    ]);

    it('filters by the operator and the time range', function (): void {
        $actor = userWithDataScope('all', 'monitor.operation-log');
        $match = OperationLog::factory()->causedBy($actor)->create(['created_at' => '2026-11-19 08:00:00']);
        OperationLog::factory()->causedBy($actor)->create(['created_at' => '2026-11-20 08:00:00']);
        OperationLog::factory()->causedBy(User::factory()->create())->create(['created_at' => '2026-11-19 08:00:00']);

        $this->actingAs($actor)
            ->getJson("/api/admin/monitor/operation-logs?filter[user_id]={$actor->id}&filter[created_at]=2026-11-19T00:00:00Z,2026-11-19T23:59:59Z")
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$match->id]);
    });

    it('rejects invalid filters and sorts with 422', function (string $query, string $field): void {
        $this->actingAs(userWithDataScope('all', 'monitor.operation-log'))
            ->getJson("/api/admin/monitor/operation-logs?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'method' => ['filter[method]=GET', 'filter.method'],
        'status' => ['filter[status]=abc', 'filter.status'],
        'time range' => ['filter[created_at]=yesterday', 'filter.created_at'],
        'unknown filter' => ['filter[payload]=x', 'filter'],
        'unknown sort' => ['sort=duration_ms', 'sort'],
    ]);
});

describe('show', function (): void {
    it('returns 403 without the monitor.operation-log code', function (): void {
        $log = OperationLog::factory()->create();

        $this->actingAs(userWithDataScope('all', 'monitor.login-log'))
            ->getJson("/api/admin/monitor/operation-logs/{$log->id}")
            ->assertForbidden();
    });

    it('shows the masked payload and route parameters', function (): void {
        $actor = userWithDataScope('all', 'monitor.operation-log');
        $log = OperationLog::factory()->causedBy($actor)->withProperties(['route_parameters' => ['user' => '5']])->create();

        $response = $this->actingAs($actor)
            ->getJson("/api/admin/monitor/operation-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.route_parameters', ['user' => '5']);

        expect($response->json('data.payload'))->toBeIgnoringKeyOrder(['username' => 'alice', 'password' => '******']);
    });

    it('returns 403 outside of the data scope', function (): void {
        $log = OperationLog::factory()->causedBy(User::factory()->create())->create();

        $this->actingAs(userWithPermissions('monitor.operation-log'))
            ->getJson("/api/admin/monitor/operation-logs/{$log->id}")
            ->assertForbidden();
    });

    it('returns 404 for other logs', function (): void {
        $log = LoginLog::factory()->create();

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/monitor/operation-logs/{$log->id}")
            ->assertNotFound();
    });
});
