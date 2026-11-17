<?php

use App\Models\{LoginLog, OperationLog, Position};

beforeEach(function () {
    $this->admin = actingAsUser(superAdmin());
});

test('non get requests of authenticated users are recorded with masked payload', function () {
    $this->postJson('/api/users', [
        'username' => 'logged', 'name' => 'x', 'password' => 'secret-password', 'status' => 'active',
        'role_ids' => [], 'position_ids' => [],
    ])->assertCreated();

    $log = OperationLog::query()->sole();

    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->method)->toBe('POST')
        ->and($log->path)->toBe('/api/users')
        ->and($log->route_name)->toBe('users.store')
        ->and($log->summary)->toBe('新增用户')
        ->and($log->status_code)->toBe(201)
        ->and($log->payload['password'])->toBe('******')
        ->and($log->payload['username'])->toBe('logged');
});

test('every api response carries a request id that is also stored on the operation log', function () {
    $requestId = $this->postJson('/api/positions', ['name' => 'P', 'code' => 'p', 'status' => 'active'])
        ->assertCreated()
        ->headers->get('X-Request-Id');

    expect($requestId)->toBeUuid()
        ->and(OperationLog::query()->sole()->request_id)->toBe($requestId);

    $this->withHeader('X-Request-Id', $given = (string) Str::uuid())->getJson('/api/positions')->assertHeader('X-Request-Id', $given);
    $this->withHeader('X-Request-Id', 'not-a-uuid')->getJson('/api/positions')->assertHeader('X-Request-Id');
});

test('get requests are not recorded', function () {
    $this->getJson('/api/users')->assertOk();

    expect(OperationLog::query()->count())->toBe(0);
});

test('guest requests are not recorded', function () {
    $this->app['auth']->forgetGuards();
    $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'x']);

    expect(OperationLog::query()->count())->toBe(0)
        ->and(LoginLog::query()->count())->toBe(1);
});

test('operation logs can be listed, shown and cleared', function () {
    $this->deleteJson('/api/positions/'.Position::factory()->create()->id)->assertNoContent();
    $log = OperationLog::query()->sole();

    $this->getJson('/api/operation-logs?method=DELETE')
        ->assertOk()
        ->assertJsonPath('data.0.user', ['id' => $this->admin->id, 'name' => $this->admin->name]);
    $this->getJson("/api/operation-logs/{$log->id}")
        ->assertOk()
        ->assertJsonPath('data.path', $log->path);

    expect($this->getJson("/api/operation-logs/{$log->id}")->getContent())->toContain('"payload":{}');
    $this->deleteJson('/api/operation-logs')->assertNoContent();

    // 清空请求本身会在响应后被记录
    expect(OperationLog::query()->pluck('route_name')->all())->toBe(['operation-logs.clear']);
});

test('login logs can be listed and deleted', function () {
    $log = LoginLog::factory()->create(['username' => 'someone']);
    LoginLog::factory()->create(['username' => 'other']);

    $this->getJson('/api/login-logs?username=some')->assertOk()->assertJsonCount(1, 'data');
    $this->deleteJson("/api/login-logs/{$log->id}")->assertNoContent();
    $this->deleteJson('/api/login-logs')->assertNoContent();

    expect(LoginLog::query()->count())->toBe(0);
});
