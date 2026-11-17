<?php

use App\Enums\{LoginStatus, Status};
use App\Models\{LoginLog, User};
use Illuminate\Support\Facades\Hash;

test('user can login and receives a bearer token', function () {
    $user = User::factory()->create(['username' => 'alice']);

    $response = $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'password']);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at']])
        ->assertJsonPath('data.token_type', 'Bearer');

    expect($user->tokens()->count())->toBe(1)
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and(LoginLog::query()->where('status', LoginStatus::Success)->where('user_id', $user->id)->exists())->toBeTrue();
});

test('login fails with wrong password and records a failed log', function () {
    User::factory()->create(['username' => 'alice']);

    $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('username');

    expect(LoginLog::query()->where('status', LoginStatus::Failed)->where('username', 'alice')->exists())->toBeTrue();
});

test('disabled user cannot login', function () {
    User::factory()->disabled()->create(['username' => 'bob']);

    $this->postJson('/api/auth/login', ['username' => 'bob', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('username');
});

test('failed logins are rate limited per username and ip', function () {
    User::factory()->create(['username' => 'alice']);

    foreach (range(1, config()->integer('edge.auth.login_max_attempts')) as $attempt) {
        $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'password'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, '登录尝试过于频繁'));

    // 计数按"用户名 + IP"区分，其他账号不受影响
    User::factory()->create(['username' => 'bob']);
    $this->postJson('/api/auth/login', ['username' => 'bob', 'password' => 'password'])->assertOk();
});

test('successful logins do not count towards the login rate limit', function () {
    User::factory()->create(['username' => 'alice']);

    foreach (range(1, config()->integer('edge.auth.login_max_attempts') + 1) as $attempt) {
        $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'password'])->assertOk();
    }
});

test('a successful login clears earlier failed attempts', function () {
    User::factory()->create(['username' => 'alice']);
    $failures = config()->integer('edge.auth.login_max_attempts') - 1;

    foreach (range(1, $failures) as $attempt) {
        $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'wrong']);
    }

    $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'password'])->assertOk();

    foreach (range(1, $failures) as $attempt) {
        $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/auth/login', ['username' => 'alice', 'password' => 'password'])->assertOk();
});

test('the login rate limit message is localized', function () {
    config(['edge.auth.login_max_attempts' => 1]);

    $this->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'wrong'])->assertUnprocessable();
    $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/auth/login', ['username' => 'nobody', 'password' => 'wrong'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', fn (string $message): bool => str_starts_with($message, 'Too many login attempts.'));
});

test('guests receive 401', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized()->assertJsonStructure(['message']);
});

test('existing tokens of a disabled user are rejected', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/auth/me')->assertOk();

    $user->update(['status' => Status::Disabled]);
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

test('me returns the current user contract', function () {
    $user = actingAsUser(superAdmin(['name' => '超级管理员']));

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'id', 'username', 'name', 'email', 'phone', 'avatar', 'status', 'is_super_admin',
            'department', 'roles' => [['id', 'name', 'code']], 'positions', 'permissions',
            'data_scope', 'unread_notifications_count', 'last_login_at',
        ]])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.is_super_admin', true)
        ->assertJsonPath('data.data_scope', 'all');
});

test('user can update profile', function () {
    actingAsUser(User::factory()->create());

    $this->putJson('/api/auth/profile', ['name' => '新名字', 'email' => 'new@example.com', 'phone' => '13800000000'])
        ->assertOk()
        ->assertJsonPath('data.name', '新名字')
        ->assertJsonPath('data.email', 'new@example.com');
});

test('user can change password with the current password', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->putJson('/api/auth/password', [
        'current_password' => 'wrong',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->withToken($token)->putJson('/api/auth/password', [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertNoContent();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
