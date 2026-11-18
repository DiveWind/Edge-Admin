<?php

use App\Core\System\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 后台入口：按 CORE_APPS 加载、路由前缀、Vben 格式的异常输出、限流
|--------------------------------------------------------------------------
*/

describe('CORE_APPS', function (): void {
    it('loads the admin entry when CORE_APPS includes admin', function (string $apps): void {
        $this->refreshApplicationWithEnv(['CORE_APPS' => $apps]);

        expect(config('core.apps.admin.enabled'))->toBeTrue()
            ->and(Route::has('admin.ping'))->toBeTrue()
            ->and(Route::getMiddlewareGroups())->toHaveKey('admin');

        $this->getJson('/api/admin/ping')->assertOk()->assertExactJson(['code' => 0, 'data' => ['pong' => true], 'message' => 'ok']);
    })->with([
        'admin' => 'admin',
        'with spaces and case' => ' Device , ADMIN ',
    ]);

    it('loads all entries when CORE_APPS is not set', function (): void {
        $config = $this->withEnv(['CORE_APPS' => null], fn (): array => require base_path('config/core.php'));

        expect($config['apps'])->each->toMatchArray(['enabled' => true]);
    });

    it('registers nothing for the admin entry when CORE_APPS does not include it', function (string $apps): void {
        $this->refreshApplicationWithEnv(['CORE_APPS' => $apps]);

        expect(config('core.apps.admin.enabled'))->toBeFalse()
            ->and(Route::has('admin.ping'))->toBeFalse()
            ->and(Route::has('login.store'))->toBeFalse()
            ->and(Route::getMiddlewareGroups())->not->toHaveKey('admin');

        // 路由不存在，404 也不再是 Vben 格式
        $this->getJson('/api/admin/ping')->assertNotFound()->assertJsonMissingPath('code');
    })->with([
        'empty' => '',
        'other entries only' => 'device',
    ]);
});

it('uses the configurable URL prefix', function (): void {
    $this->refreshApplicationWithEnv(['CORE_ADMIN_PREFIX' => '/backend/']);

    expect(route('admin.ping', absolute: false))->toBe('/backend/ping')
        ->and(route('login.store', absolute: false))->toBe('/backend/login')
        ->and(route('admin.sanctum.csrf-cookie', absolute: false))->toBe('/backend/sanctum/csrf-cookie');

    $this->getJson('/backend/ping')->assertOk()->assertJsonPath('data.pong', true);
    $this->getJson('/backend/missing')->assertNotFound()->assertJsonPath('code', 404);
    $this->getJson('/api/admin/ping')->assertNotFound()->assertJsonMissingPath('code');
});

describe('exceptions', function (): void {
    beforeEach(function (): void {
        Route::middleware('admin')->prefix('api/admin/_test')->group(function (): void {
            Route::get('auth', fn (): string => 'secret')->middleware('auth');
            Route::get('forbidden', fn (): never => throw new AuthorizationException);
            Route::get('abort/{status}', fn (int $status) => abort($status))->whereNumber('status');
            Route::get('abort-message', fn () => abort(409, 'Version conflict'));
            Route::get('model', fn () => User::query()->findOrFail(999));
            Route::post('only-post', fn (): string => 'ok');
            Route::get('csrf', fn (): never => throw new TokenMismatchException('CSRF token mismatch.'));
            Route::post('validate', fn (Request $request) => $request->validate(['name' => ['required', 'string']]));
            Route::get('boom', fn (): never => throw new RuntimeException('Secret database password'));
            Route::get('http-response', fn (): never => throw new HttpResponseException(response()->json(['message' => 'Custom', 'reason' => 'locked'], 423)));
        });
    });

    it('returns 401 without redirecting guests', function (string $accept): void {
        $this->get('/api/admin/_test/auth', ['Accept' => $accept])
            ->assertUnauthorized()
            ->assertExactJson(['code' => 401, 'data' => null, 'message' => 'Unauthenticated.']);
    })->with(['application/json', 'text/html']);

    it('returns 403 for authorization failures', function (): void {
        $this->getJson('/api/admin/_test/forbidden')
            ->assertForbidden()
            ->assertExactJson(['code' => 403, 'data' => null, 'message' => 'This action is unauthorized.']);

        $this->getJson('/api/admin/_test/abort/403')->assertExactJson(['code' => 403, 'data' => null, 'message' => 'Forbidden']);
    });

    it('returns 404 without leaking routes or model names', function (string $uri): void {
        $this->getJson($uri)
            ->assertNotFound()
            ->assertExactJson(['code' => 404, 'data' => null, 'message' => 'Not Found']);
    })->with([
        'unknown route' => '/api/admin/missing',
        'prefix itself' => '/api/admin',
        'model not found' => '/api/admin/_test/model',
        'route parameter format mismatch' => '/api/admin/_test/abort/abc',
    ]);

    it('returns 405 for unsupported methods', function (): void {
        $this->getJson('/api/admin/_test/only-post')
            ->assertMethodNotAllowed()
            ->assertExactJson(['code' => 405, 'data' => null, 'message' => 'Method Not Allowed']);
    });

    it('returns 419 for CSRF token mismatches', function (): void {
        $this->getJson('/api/admin/_test/csrf')
            ->assertStatus(419)
            ->assertExactJson(['code' => 419, 'data' => null, 'message' => 'CSRF token mismatch.']);
    });

    it('returns 422 with field errors', function (): void {
        $this->postJson('/api/admin/_test/validate', [])
            ->assertUnprocessable()
            ->assertExactJson([
                'code' => 422,
                'data' => null,
                'message' => 'The given data was invalid.',
                'errors' => ['name' => ['The name field is required.']],
            ]);
    });

    it('keeps the message of HTTP exceptions other than 404 and 405', function (): void {
        $this->getJson('/api/admin/_test/abort-message')
            ->assertStatus(409)
            ->assertExactJson(['code' => 409, 'data' => null, 'message' => 'Version conflict']);
    });

    it('hides server errors in production', function (): void {
        config(['app.debug' => false]);

        $this->getJson('/api/admin/_test/boom')
            ->assertInternalServerError()
            ->assertExactJson(['code' => 500, 'data' => null, 'message' => 'Internal Server Error']);
    });

    it('includes debug information for server errors in debug mode', function (): void {
        config(['app.debug' => true]);

        $this->getJson('/api/admin/_test/boom')
            ->assertInternalServerError()
            ->assertJsonPath('code', 500)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Secret database password')
            ->assertJsonPath('debug.exception', RuntimeException::class);
    });

    it('wraps the response carried by an HttpResponseException', function (): void {
        $this->getJson('/api/admin/_test/http-response')
            ->assertStatus(423)
            ->assertExactJson(['code' => 423, 'data' => null, 'message' => 'Custom', 'reason' => 'locked']);
    });

    it('translates messages with the application locale', function (): void {
        app()->setLocale('zh_CN');

        $this->getJson('/api/admin/missing')->assertJsonPath('message', '资源不存在');
        $this->postJson('/api/admin/_test/validate', [])
            ->assertJsonPath('message', '数据校验失败')
            ->assertJsonPath('errors.name.0', '名称 不能为空。');
    });

    it('does not affect other paths', function (): void {
        $this->getJson('/missing')->assertNotFound()->assertJsonMissingPath('code');
        $this->get('/up')->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $this->getJson('/')->assertOk()->assertExactJson(['name' => config('app.name')]);
    });
});

it('rate limits requests per minute', function (): void {
    config(['core.apps.admin.rate_limit' => 2]);

    $this->getJson('/api/admin/ping')->assertOk();
    $this->getJson('/api/admin/ping')->assertOk();

    $this->getJson('/api/admin/ping')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertExactJson(['code' => 429, 'data' => null, 'message' => 'Too Many Attempts.']);
});

it('provides the response()->vben() macro', function (): void {
    Route::middleware('admin')->post('api/admin/_test/saved', fn () => response()->vben(['id' => 1], '已保存', 201));

    $this->postJson('/api/admin/_test/saved')
        ->assertCreated()
        ->assertExactJson(['code' => 0, 'data' => ['id' => 1], 'message' => '已保存']);
});

describe('Fortify', function (): void {
    it('registers the API routes of the enabled features only', function (): void {
        expect(Route::has('login.store'))->toBeTrue()
            ->and(Route::has('two-factor.login.store'))->toBeTrue()
            ->and(Route::has('password.email'))->toBeTrue()
            ->and(Route::has('user-password.update'))->toBeTrue()
            ->and(Route::has('register.store'))->toBeFalse()
            ->and(Route::has('passkey.login'))->toBeFalse()
            ->and(Route::has('verification.send'))->toBeFalse()
            // 不注册返回页面的路由
            ->and(Route::has('login'))->toBeFalse();
    });

    it('registers the email verification routes when enabled', function (): void {
        $this->refreshApplicationWithEnv(['CORE_EMAIL_VERIFICATION' => 'true']);

        expect(Route::has('verification.send'))->toBeTrue()
            ->and(Route::has('verification.verify'))->toBeTrue();
    });

    it('answers with JSON even when the client does not ask for it', function (): void {
        User::factory()->create(['username' => 'alice']);

        $this->post('/api/admin/login', ['username' => 'alice', 'password' => 'password'], ['Accept' => 'text/html'])
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => ['two_factor' => false], 'message' => 'ok']);
    });
});
