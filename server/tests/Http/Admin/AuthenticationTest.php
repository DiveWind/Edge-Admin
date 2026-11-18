<?php

use App\Core\System\Models\User;
use Database\Seeders\DatabaseSeeder;
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| 后台登录与登出（Sanctum SPA 会话 + Fortify，设计文档 §6.2、§7）
|--------------------------------------------------------------------------
|
| 请求经由 VbenClient：带 Referer、Cookie 与 X-XSRF-TOKEN，CSRF 校验生效，登录状态只来自会话 Cookie。
|
*/

beforeEach(function (): void {
    $this->client = new VbenClient($this);
});

describe('CSRF cookie', function (): void {
    it('issues the XSRF-TOKEN cookie with an empty Vben response', function (): void {
        $response = $this->client->get('/api/admin/sanctum/csrf-cookie')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok'])
            ->assertCookie('XSRF-TOKEN');

        $session = $response->getCookie(config()->string('session.cookie'), false);

        expect($session?->isHttpOnly())->toBeTrue()
            ->and($session?->getSameSite())->toBe('lax')
            ->and($response->getCookie('XSRF-TOKEN', false)?->isHttpOnly())->toBeFalse();
    });

    it('rejects writes without the CSRF token with 419', function (): void {
        $user = User::factory()->create();

        $this->client->get('/api/admin/sanctum/csrf-cookie');

        $this->client->withoutCsrfToken()
            ->post('/api/admin/login', ['username' => $user->username, 'password' => 'password'])
            ->assertStatus(419)
            ->assertExactJson(['code' => 419, 'data' => null, 'message' => 'CSRF token mismatch.']);

        $this->client->get('/api/admin/user/info')->assertUnauthorized();
    });
});

describe('login', function (): void {
    it('logs in with the username and password', function (): void {
        $user = User::factory()->create(['username' => 'alice']);

        $this->client->login('alice')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => ['two_factor' => false], 'message' => 'ok']);

        $this->client->get('/api/admin/user/info')->assertOk()->assertJsonPath('data.userId', (string) $user->id);
    });

    it('accepts the username in any letter case', function (): void {
        User::factory()->create(['username' => 'alice']);

        $this->client->login('Alice')->assertOk();
    });

    it('rejects wrong credentials on the username field', function (string $username, string $password): void {
        User::factory()->create(['username' => 'alice']);

        $this->client->login($username, $password)
            ->assertUnprocessable()
            ->assertExactJson([
                'code' => 422,
                'data' => null,
                'message' => 'The given data was invalid.',
                'errors' => ['username' => ['These credentials do not match our records.']],
            ]);

        $this->client->get('/api/admin/user/info')->assertUnauthorized();
    })->with([
        'wrong password' => ['alice', 'wrong-password'],
        'unknown username' => ['bob', 'password'],
    ]);

    it('requires the username and password', function (): void {
        $this->client->get('/api/admin/sanctum/csrf-cookie');

        $this->client->post('/api/admin/login')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);
    });

    it('rejects a disabled account only after the password matches', function (): void {
        User::factory()->disabled()->create(['username' => 'alice']);

        $this->client->login('alice', 'wrong-password')
            ->assertUnprocessable()
            ->assertJsonPath('errors.username', ['These credentials do not match our records.']);

        $this->client->login('alice')
            ->assertUnprocessable()
            ->assertExactJson([
                'code' => 422,
                'data' => null,
                'message' => 'The given data was invalid.',
                'errors' => ['username' => ['This account has been disabled.']],
            ]);

        $this->client->get('/api/admin/user/info')->assertUnauthorized();
    });

    it('throttles login attempts per username and IP address', function (): void {
        $this->freezeTime();
        config(['core.auth.login_rate_limit' => 2]);
        User::factory()->create(['username' => 'alice']);

        $this->client->login('alice', 'wrong-password')->assertUnprocessable();
        $this->client->login('alice', 'wrong-password')->assertUnprocessable();

        $message = 'Too many login attempts. Please try again in 60 seconds.';

        $this->client->login('alice')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '60')
            ->assertExactJson(['code' => 429, 'data' => null, 'message' => $message, 'errors' => ['username' => [$message]]]);

        // 其他用户名不受影响
        User::factory()->create(['username' => 'bob']);

        $this->client->login('bob')->assertOk();
    });

    it('translates the messages with the application locale', function (): void {
        app()->setLocale('zh_CN');
        User::factory()->disabled()->create(['username' => 'alice']);

        $this->client->login('alice', 'wrong-password')
            ->assertJsonPath('message', '数据校验失败')
            ->assertJsonPath('errors.username', ['用户名或密码错误。']);

        $this->client->login('alice')->assertJsonPath('errors.username', ['账号已停用']);
    });

    it('answers 409 instead of redirecting when already logged in', function (): void {
        User::factory()->create(['username' => 'alice']);
        $this->client->login('alice')->assertOk();

        $this->client->post('/api/admin/login', ['username' => 'alice', 'password' => 'password'])
            ->assertConflict()
            ->assertExactJson(['code' => 409, 'data' => null, 'message' => 'Already authenticated.']);

        $this->client->post('/api/admin/forgot-password', ['email' => 'alice@example.com'])->assertConflict();
    });

    it('logs in the accounts created by the seeder', function (): void {
        $this->seed(DatabaseSeeder::class);

        $this->client->login('admin')->assertOk();
        $this->client->post('/api/admin/logout')->assertOk();

        $this->client->login('disabled')->assertJsonPath('errors.username', ['This account has been disabled.']);
    });
});

describe('logout', function (): void {
    it('logs out with an empty Vben response instead of 204', function (): void {
        User::factory()->create(['username' => 'alice']);
        $this->client->login('alice');

        $this->client->post('/api/admin/logout')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        $this->client->get('/api/admin/user/info')->assertUnauthorized();
    });

    it('requires a logged-in session', function (): void {
        $this->client->get('/api/admin/sanctum/csrf-cookie');

        $this->client->post('/api/admin/logout')
            ->assertUnauthorized()
            ->assertExactJson(['code' => 401, 'data' => null, 'message' => 'Unauthenticated.']);
    });
});

it('does not start a session for requests from other origins', function (): void {
    User::factory()->create(['username' => 'alice']);

    $this->withHeader('Referer', 'https://evil.example/')
        ->postJson('/api/admin/login', ['username' => 'alice', 'password' => 'password'])
        ->assertCookieMissing(config()->string('session.cookie'));
});
