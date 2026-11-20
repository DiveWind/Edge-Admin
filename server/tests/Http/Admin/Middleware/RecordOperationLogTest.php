<?php

use App\Core\Monitor\Actions\LogOperation;
use App\Core\Monitor\Models\OperationLog;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\{DB, Exceptions};
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| 操作日志：后台入口的 RecordOperationLog 中间件（设计文档 §6.6）
|--------------------------------------------------------------------------
|
| 用后台入口真实的接口发请求：用户管理（system.user.*）与个人设置（Fortify）。脱敏的完整矩阵见 tests/Core/Monitor/Actions/LogOperationTest.php。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->actor = superAdminActor();
});

/**
 * 唯一的一条操作日志。
 */
function soleOperationLog(): OperationLog
{
    return OperationLog::query()->sole();
}

it('records a write request with the operator, route, status, duration and client', function (): void {
    $response = $this->actingAs($this->actor)
        ->withHeader('User-Agent', 'Pest Browser')
        ->postJson('/api/admin/system/users', ['username' => 'Alice', 'name' => 'Alice', 'password' => 'password'])
        ->assertCreated();

    $log = soleOperationLog();

    expect($log)
        ->description->toBe('admin.system.users.store')
        ->causer_type->toBe('user')
        ->causer_id->toBe($this->actor->id)
        ->and($log->properties->except('duration_ms')->all())->toBeIgnoringKeyOrder([
            'username' => $this->actor->username,
            'method' => 'POST',
            'path' => '/api/admin/system/users',
            'route_parameters' => [],
            'payload' => ['username' => 'Alice', 'name' => 'Alice', 'password' => '******'],
            'status' => 201,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest Browser',
            'request_id' => $response->headers->get('X-Request-Id'),
        ])
        ->and($log->getProperty('duration_ms'))->toBeInt()->toBeGreaterThanOrEqual(0);
});

it('records the route parameters and failed requests', function (): void {
    $this->actingAs($this->actor)
        ->deleteJson("/api/admin/system/users/{$this->actor->id}")
        ->assertForbidden();

    expect(soleOperationLog())
        ->description->toBe('admin.system.users.destroy')
        ->and(soleOperationLog()->properties->only(['method', 'route_parameters', 'status'])->all())
        ->toBeIgnoringKeyOrder(['method' => 'DELETE', 'route_parameters' => ['user' => (string) $this->actor->id], 'status' => 403]);
});

it('records validation failures with the submitted payload', function (): void {
    $other = User::factory()->create();

    $this->actingAs($this->actor)
        ->putJson("/api/admin/system/users/{$other->id}/status", ['status' => 'unknown', 'nested' => ['token' => 'abc']])
        ->assertUnprocessable();

    expect(soleOperationLog()->properties->only(['payload', 'status'])->all())
        ->toBeIgnoringKeyOrder(['payload' => ['status' => 'unknown', 'nested' => ['token' => '******']], 'status' => 422]);
});

it('does not record read requests', function (): void {
    $this->actingAs($this->actor)->getJson('/api/admin/system/users')->assertOk();

    expect(OperationLog::query()->count())->toBe(0);
});

it('does not record write requests of guests', function (): void {
    $this->postJson('/api/admin/system/users', ['username' => 'alice'])->assertUnauthorized();
    $this->postJson('/api/admin/forgot-password', ['email' => 'alice@example.com'])->assertOk();

    expect(OperationLog::query()->count())->toBe(0);
});

it('records the profile settings but not the login flow', function (): void {
    $client = new VbenClient($this);
    User::factory()->create(['username' => 'alice']);

    $client->login('alice')->assertOk();
    $client->put('/api/admin/user/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();
    $client->post('/api/admin/logout')->assertOk();

    expect(soleOperationLog())
        ->description->toBe('user-password.update')
        ->and(soleOperationLog()->getProperty('payload'))->toBeIgnoringKeyOrder([
            'current_password' => '******',
            'password' => '******',
            'password_confirmation' => '******',
        ])
        ->and(DB::table('activity_log')->pluck('properties')->implode(''))->not->toContain('new-password');
});

it('skips the routes listed in the configuration', function (): void {
    config(['core.monitor.operation_log.except_routes' => ['admin.system.users.*']]);

    $this->actingAs($this->actor)
        ->postJson('/api/admin/system/users', ['username' => 'alice', 'name' => 'Alice', 'password' => 'password'])
        ->assertCreated();

    expect(OperationLog::query()->count())->toBe(0);
});

it('responds normally when the operation log cannot be written', function (): void {
    Exceptions::fake();
    $this->mock(LogOperation::class)->shouldReceive('handle')->andThrow(new RuntimeException('log store is down'));

    $this->actingAs($this->actor)
        ->postJson('/api/admin/system/users', ['username' => 'alice', 'name' => 'Alice', 'password' => 'password'])
        ->assertCreated()
        ->assertJsonPath('data.username', 'alice');

    expect(User::query()->where('username', 'alice')->exists())->toBeTrue();
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'log store is down');
});
