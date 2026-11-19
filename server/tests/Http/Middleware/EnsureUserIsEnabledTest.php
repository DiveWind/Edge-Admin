<?php

use App\Core\System\Actions\DisableUser;
use App\Core\System\Models\User;
use App\Http\Middleware\EnsureUserIsEnabled;
use Illuminate\Support\Facades\{DB, Route};
use Tests\Http\Admin\VbenClient;

/*
|--------------------------------------------------------------------------
| 停用账号立即生效（设计文档 §7.3）
|--------------------------------------------------------------------------
|
| 中间件在后台入口的中间件组中，对组内全部路由生效（包括 Fortify 的路由与不要求登录的路由）。
|
*/

beforeEach(function (): void {
    $this->user = User::factory()->create(['username' => 'alice']);
});

/**
 * 绕过 Action 直接改库（如在数据库中手工停用），只有中间件能发现。
 */
function disableInDatabase(User $user): void
{
    DB::table('users')->where('id', $user->id)->update(['status' => 'disabled']);
}

it('logs out the session of a user disabled outside the action and returns 401', function (string $method, string $uri): void {
    $client = new VbenClient($this);
    $client->login('alice')->assertOk();
    disableInDatabase($this->user);

    $client->{$method}($uri)
        ->assertUnauthorized()
        ->assertExactJson(['code' => 401, 'data' => null, 'message' => 'This account has been disabled.']);

    expect(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0);

    // 会话已注销：之后的请求是未登录状态
    $client->get('/api/admin/user/info')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
})->with([
    'own route' => ['get', '/api/admin/user/info'],
    'Fortify route' => ['put', '/api/admin/user/profile-information'],
    'public route' => ['get', '/api/admin/sanctum/csrf-cookie'],
]);

it('takes effect immediately when the user is disabled through the action', function (): void {
    $browser = new VbenClient($this);
    $browser->login('alice')->assertOk();
    $other = new VbenClient($this);
    $other->login('alice')->assertOk();

    resolve(DisableUser::class)->handle($this->user);

    $browser->get('/api/admin/user/info')->assertUnauthorized();
    $other->get('/api/admin/user/info')->assertUnauthorized();
});

it('revokes the tokens of a disabled user', function (): void {
    $token = $this->user->createToken('device')->plainTextToken;
    disableInDatabase($this->user);

    $this->withToken($token)
        ->getJson('/api/admin/user/info')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'This account has been disabled.');

    expect($this->user->tokens()->count())->toBe(0);
});

it('lets requests of enabled users and guests through', function (): void {
    Route::middleware(EnsureUserIsEnabled::class)->get('_test/enabled', fn (): string => 'ok');

    $this->get('/_test/enabled')->assertOk();
    $this->actingAs($this->user)->get('/_test/enabled')->assertOk();
});
