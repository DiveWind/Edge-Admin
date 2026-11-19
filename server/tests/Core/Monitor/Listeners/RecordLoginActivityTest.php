<?php

use App\Core\Monitor\Actions\LogLogin;
use App\Core\Monitor\Models\{Activity, LoginLog, OperationLog, Session};
use App\Core\System\Models\User;
use Illuminate\Support\Facades\{DB, Exceptions, Password};
use PragmaRX\Google2FA\Google2FA;
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| 登录日志：认证事件写入 activitylog 的 login 日志（设计文档 §6.6、§7.3）
|--------------------------------------------------------------------------
|
| 通过 VbenClient 走完整的登录流程（Fortify 的路由、限流器、两步验证），检查写入的日志。
|
*/

beforeEach(function (): void {
    $this->client = new VbenClient($this);
});

/**
 * 唯一的一条登录日志。
 */
function soleLoginLog(): LoginLog
{
    return LoginLog::query()->sole();
}

it('records a successful login with the account, IP, user agent and request id', function (): void {
    $alice = User::factory()->create(['username' => 'alice']);

    $response = $this->client->login('alice')->assertOk();

    expect(soleLoginLog())
        ->event->toBe('login')
        ->description->toBe('login')
        ->causer_id->toBe($alice->id)
        ->causer_type->toBe('user')
        ->and(soleLoginLog()->properties->all())->toBe([
            'username' => 'alice',
            'reason' => null,
            'via_remember' => false,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Symfony',
            'request_id' => $response->headers->get('X-Request-Id'),
        ]);
});

it('records a logout', function (): void {
    $alice = User::factory()->create(['username' => 'alice']);
    $this->client->login('alice');

    $this->client->post('/api/admin/logout')->assertOk();

    expect(LoginLog::query()->orderBy('id')->pluck('event')->all())->toBe(['login', 'logout'])
        ->and(LoginLog::query()->where('event', 'logout')->sole()->causer_id)->toBe($alice->id);
});

it('records a wrong password against the account without the password', function (): void {
    $alice = User::factory()->create(['username' => 'alice']);

    $this->client->login('Alice', 'secret-attempt')->assertUnprocessable();

    expect(soleLoginLog())
        ->event->toBe('failed')
        ->causer_id->toBe($alice->id)
        ->and(soleLoginLog()->stringProperty('reason'))->toBe('invalid_credentials')
        ->and(soleLoginLog()->stringProperty('username'))->toBe('alice')
        ->and(DB::table('activity_log')->pluck('properties')->implode(''))->not->toContain('secret-attempt');
});

it('records an unknown username without an account', function (): void {
    $this->client->login('Nobody', 'secret-attempt')->assertUnprocessable();

    expect(soleLoginLog())
        ->event->toBe('failed')
        ->causer_id->toBeNull()
        ->causer_type->toBeNull()
        ->and(soleLoginLog()->stringProperty('username'))->toBe('nobody')
        ->and(soleLoginLog()->stringProperty('reason'))->toBe('invalid_credentials')
        ->and(DB::table('activity_log')->pluck('properties')->implode(''))->not->toContain('secret-attempt');
});

it('records a login to a disabled account with the right password', function (): void {
    $alice = User::factory()->disabled()->create(['username' => 'alice']);

    $this->client->login('alice', 'password')->assertUnprocessable();

    expect(soleLoginLog())
        ->event->toBe('failed')
        ->causer_id->toBe($alice->id)
        ->and(soleLoginLog()->stringProperty('reason'))->toBe('disabled')
        ->and(DB::table('activity_log')->pluck('properties')->implode(''))->not->toContain('"password"');
});

it('records a lockout when the login is throttled', function (): void {
    $this->freezeTime();
    config(['core.auth.login_rate_limit' => 1]);
    $alice = User::factory()->create(['username' => 'alice']);

    $this->client->login('alice', 'wrong-password')->assertUnprocessable();
    $this->client->login('alice', 'locked-out-attempt')->assertTooManyRequests();

    expect(LoginLog::query()->orderBy('id')->pluck('event')->all())->toBe(['failed', 'lockout'])
        ->and(LoginLog::query()->where('event', 'lockout')->sole())
        ->causer_id->toBe($alice->id)
        ->and(DB::table('activity_log')->pluck('properties')->implode(''))->not->toContain('locked-out-attempt');
});

it('records a wrong two-factor code, and the login only after the challenge', function (): void {
    $alice = User::factory()->withTwoFactorAuthentication()->create(['username' => 'alice']);

    $this->client->login('alice')->assertOk();
    $this->client->post('/api/admin/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();

    expect(soleLoginLog())
        ->event->toBe('failed')
        ->causer_id->toBe($alice->id)
        ->and(soleLoginLog()->stringProperty('reason'))->toBe('two_factor');

    $code = resolve(Google2FA::class)->getCurrentOtp(decrypt($alice->refresh()->two_factor_secret));
    $this->client->post('/api/admin/two-factor-challenge', ['code' => $code])->assertOk();

    expect(LoginLog::query()->orderBy('id')->pluck('event')->all())->toBe(['failed', 'login']);
});

it('does not record login and logout requests as operations', function (): void {
    User::factory()->create(['username' => 'alice']);

    $this->client->login('alice');
    $this->client->post('/api/admin/logout');

    expect(OperationLog::query()->count())->toBe(0)
        ->and(Activity::query()->count())->toBe(2);
});

it('logs in even when the login log cannot be written', function (): void {
    Exceptions::fake();
    User::factory()->create(['username' => 'alice']);
    $this->mock(LogLogin::class)->shouldReceive('handle')->andThrow(new RuntimeException('log store is down'));

    $this->client->login('alice')->assertOk();

    $this->client->get('/api/admin/user/info')->assertOk();
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'log store is down');
});

it('marks a login restored from the remember-me cookie', function (): void {
    $alice = User::factory()->create(['username' => 'alice']);
    $this->client->get('/api/admin/sanctum/csrf-cookie');
    $this->client->post('/api/admin/login', ['username' => 'alice', 'password' => 'password', 'remember' => true])->assertOk();

    // 会话过期（会话表中的记录被清理），浏览器只剩“记住我”的 recaller Cookie
    Session::query()->delete();

    $this->client->get('/api/admin/user/info')->assertOk()->assertJsonPath('data.username', 'alice');

    expect(LoginLog::query()->orderBy('id')->get()->map(fn (LoginLog $log): array => [$log->event, $log->getProperty('via_remember'), $log->causer_id])->all())
        ->toBe([['login', false, $alice->id], ['login', true, $alice->id]]);
});

it('records a password reset without the password or the token', function (): void {
    $alice = User::factory()->create(['username' => 'alice', 'email' => 'alice@example.com']);
    $token = Password::broker()->createToken($alice);
    $this->client->get('/api/admin/sanctum/csrf-cookie');

    $this->client->post('/api/admin/reset-password', [
        'token' => $token,
        'email' => 'alice@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk();

    expect(soleLoginLog())
        ->event->toBe('password_reset')
        ->causer_id->toBe($alice->id)
        ->and(soleLoginLog()->properties->keys()->all())->toBe(['username', 'reason', 'ip_address', 'user_agent', 'request_id'])
        ->and(DB::table('activity_log')->pluck('properties')->implode(''))->not->toContain('brand-new-password', $token);
});
