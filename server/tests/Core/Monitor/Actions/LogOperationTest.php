<?php

use App\Core\Monitor\Actions\LogOperation;
use App\Core\Monitor\Models\OperationLog;
use App\Core\System\Models\User;

/*
|--------------------------------------------------------------------------
| 写入操作日志与脱敏（设计文档 §6.6）
|--------------------------------------------------------------------------
*/

/**
 * 用默认配置脱敏。
 *
 * @param  array<array-key, mixed>  $data
 * @return array<array-key, mixed>
 */
function maskPayload(array $data): array
{
    return resolve(LogOperation::class)->mask($data);
}

it('masks the sensitive keys at any depth, including lists', function (): void {
    expect(maskPayload([
        'username' => 'alice',
        'Password' => 'secret',
        'password_confirmation' => 'secret',
        'current_password' => 'secret',
        'code' => '123456',
        'recovery_code' => 'abcd-efgh',
        'profile' => ['api_token' => 'xyz', 'client_secret' => ['nested' => 'value'], 'name' => 'Alice'],
        'devices' => [['name' => 'phone', 'access_token' => 'xyz'], ['name' => 'tablet']],
    ]))->toBe([
        'username' => 'alice',
        'Password' => '******',
        'password_confirmation' => '******',
        'current_password' => '******',
        'code' => '******',
        'recovery_code' => '******',
        'profile' => ['api_token' => '******', 'client_secret' => '******', 'name' => 'Alice'],
        'devices' => [['name' => 'phone', 'access_token' => '******'], ['name' => 'tablet']],
    ]);
});

it('does not mask keys that only contain the word code', function (): void {
    expect(maskPayload(['postcode' => '100000', 'codes' => ['system.user']]))
        ->toBe(['postcode' => '100000', 'codes' => ['system.user']]);
});

it('masks the keys added in the configuration', function (): void {
    config(['core.monitor.operation_log.masked_keys' => [...config()->array('core.monitor.operation_log.masked_keys'), 'id_card*']]);

    expect(maskPayload(['id_card_number' => '110101', 'password' => 'secret', 'name' => 'Alice']))
        ->toBe(['id_card_number' => '******', 'password' => '******', 'name' => 'Alice']);
});

it('truncates long string values at any depth', function (): void {
    config(['core.monitor.operation_log.max_value_length' => 5]);

    expect(maskPayload(['title' => '一二三四五六', 'short' => 'abcde', 'items' => [['body' => 'abcdefgh']], 'password' => 'a-long-secret', 'count' => 1234567]))
        ->toBe(['title' => '一二三四五…(truncated)', 'short' => 'abcde', 'items' => [['body' => 'abcde…(truncated)']], 'password' => '******', 'count' => 1234567]);
});

it('replaces a payload that is still too large with its original size', function (): void {
    config(['core.monitor.operation_log.max_value_length' => 10, 'core.monitor.operation_log.max_payload_bytes' => 100]);
    $payload = array_fill_keys(array_map(fn (int $i): string => "field_{$i}", range(1, 20)), 'value');
    $operator = User::factory()->create();

    resolve(LogOperation::class)->handle($operator, 'admin.demo.store', [
        'method' => 'POST', 'path' => '/', 'route_parameters' => [], 'payload' => $payload, 'status' => 201,
        'duration_ms' => 0, 'ip_address' => null, 'user_agent' => null, 'request_id' => null,
    ]);

    expect(OperationLog::query()->sole()->getProperty('payload'))
        ->toBe(['_truncated' => true, '_bytes' => strlen((string) json_encode($payload))]);
});

it('keeps a payload within the size limit', function (): void {
    expect(resolve(LogOperation::class)->limitSize(['name' => 'Alice'], ['name' => 'Alice']))->toBe(['name' => 'Alice']);
});

it('writes the operation log with the masked payload and route parameters', function (): void {
    $operator = User::factory()->create(['username' => 'alice']);

    resolve(LogOperation::class)->handle($operator, 'admin.demo.update', [
        'method' => 'PUT',
        'path' => '/api/admin/demo/reset/abc',
        'route_parameters' => ['token' => 'abc'],
        'payload' => ['password' => 'secret'],
        'status' => 200,
        'duration_ms' => 3,
        'ip_address' => '10.0.0.1',
        'user_agent' => str_repeat('a', 600),
        'request_id' => 'request-0001',
    ]);

    $log = OperationLog::query()->sole();

    expect($log)
        ->description->toBe('admin.demo.update')
        ->causer_id->toBe($operator->id)
        ->and($log->getProperty('username'))->toBe('alice')
        ->and($log->getProperty('route_parameters'))->toBe(['token' => '******'])
        ->and($log->getProperty('payload'))->toBe(['password' => '******'])
        ->and($log->getProperty('request_id'))->toBe('request-0001')
        ->and($log->stringProperty('user_agent'))->toHaveLength(LogOperation::USER_AGENT_LENGTH);
});

it('writes nothing while activitylog is disabled', function (): void {
    activity()->disableLogging();

    $log = resolve(LogOperation::class)->handle(User::factory()->create(), 'admin.demo.update', [
        'method' => 'PUT', 'path' => '/', 'route_parameters' => [], 'payload' => [], 'status' => 200,
        'duration_ms' => 0, 'ip_address' => null, 'user_agent' => null, 'request_id' => null,
    ]);

    expect($log)->toBeNull()
        ->and(OperationLog::query()->count())->toBe(0);
});
