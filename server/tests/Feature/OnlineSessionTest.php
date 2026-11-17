<?php

use App\Models\{PersonalAccessToken, User};

test('login records client info on the issued token', function () {
    $user = User::factory()->create(['username' => 'alice']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.8'])
        ->withHeader('User-Agent', 'EdgeTest/1.0')
        ->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'password'])
        ->assertOk();

    $token = PersonalAccessToken::query()->sole();
    expect($token->ip)->toBe('10.0.0.8')
        ->and($token->user_agent)->toBe('EdgeTest/1.0')
        ->and($token->tokenable_id)->toBe($user->id);
});

test('online sessions list unexpired tokens and mark the current one', function () {
    $admin = userWithPermissions(['monitor.online-session.view']);
    $current = $admin->createToken('api');
    $other = User::factory()->create(['username' => 'bob']);
    $other->createToken('api')->accessToken->forceFill(['ip' => '192.168.1.2'])->save();
    $other->createToken('api', ['*'], now()->subMinute());

    $response = $this->withToken($current->plainTextToken)->getJson('/api/online-sessions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'user' => ['id', 'name', 'username'], 'ip', 'user_agent', 'last_used_at', 'created_at', 'expires_at', 'is_current']]]);

    $rows = collect($response->json('data'))->keyBy('id');
    expect($rows[$current->accessToken->id]['is_current'])->toBeTrue();

    $this->withToken($current->plainTextToken)->getJson('/api/online-sessions?username=bob')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_current', false);
    $this->withToken($current->plainTextToken)->getJson('/api/online-sessions?ip=192.168')->assertJsonCount(1, 'data');
});

test('sessions can be forced offline but not the current one', function () {
    $admin = userWithPermissions(['monitor.online-session.delete']);
    $current = $admin->createToken('api');
    $target = User::factory()->create()->createToken('api');

    $this->withToken($current->plainTextToken)
        ->deleteJson("/api/online-sessions/{$current->accessToken->id}")
        ->assertForbidden()
        ->assertJsonPath('message', '不能强制下线当前会话，请使用退出登录。');

    $this->withToken($current->plainTextToken)
        ->deleteJson("/api/online-sessions/{$target->accessToken->id}")
        ->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($target->accessToken->id)->exists())->toBeFalse();
});

test('online sessions require permissions', function () {
    actingAsUser(User::factory()->create());

    $this->getJson('/api/online-sessions')->assertForbidden();
});
