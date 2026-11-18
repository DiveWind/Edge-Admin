<?php

use Tests\Core\Generator\TemporaryProject;

/*
|--------------------------------------------------------------------------
| core:make-app：入口目录、基础测试与共享文件的登记（在隔离的临时项目中执行）
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->project = TemporaryProject::create($this->app);
});

afterEach(function (): void {
    $this->project->delete();
});

it('creates a trusted entry and registers it in the shared files', function (): void {
    $this->artisan('core:make-app', ['name' => 'portal'])->assertSuccessful();

    expect($this->project->files('app/Http/Portal'))->toBe(['app/Http/Portal/PortalServiceProvider.php', 'app/Http/Portal/routes.php'])
        ->and($this->project->read('app/Http/Portal/PortalServiceProvider.php'))
        ->toContain('namespace App\Http\Portal;', 'class PortalServiceProvider extends ServiceProvider', "public const string NAME = 'portal';", '受信任的入口')
        ->and($this->project->read('app/Http/Portal/routes.php'))->toContain("Route::middleware('auth:sanctum')->group(", "Route::get('ping'")
        ->and($this->project->read('tests/Http/Portal/PortalServiceProviderTest.php'))->toContain("getJson('/api/portal/ping')");

    $config = require $this->project->path('config/core.php');

    expect($config['apps']['portal'])->toMatchArray(['trusted' => true, 'prefix' => 'api/portal', 'rate_limit' => 60])
        ->and($config['apps'])->toHaveKey('admin')
        ->and(require $this->project->path('bootstrap/providers.php'))->toContain('App\Http\Portal\PortalServiceProvider')
        ->and($this->project->read('phpunit.xml'))->toContain(
            '<env name="CORE_APPS" value="admin,portal"/>',
            '<env name="CORE_PORTAL_PREFIX" value="api/portal"/>',
            '<env name="CORE_PORTAL_RATE_LIMIT" value="60"/>',
        )
        ->and($this->project->read('.env.example'))->toContain("CORE_APPS=admin,portal\n", '# CORE_PORTAL_PREFIX=api/portal');
});

it('creates a restricted entry with a versioned prefix', function (): void {
    $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true])->assertSuccessful();

    $config = require $this->project->path('config/core.php');

    expect($config['apps']['device'])->toMatchArray(['trusted' => false, 'prefix' => 'api/device/v1'])
        ->and($this->project->read('app/Http/Device/DeviceServiceProvider.php'))->toContain('受限的入口', '只能通过模块的 Contracts')
        ->and($this->project->read('phpunit.xml'))->toContain('<env name="CORE_DEVICE_PREFIX" value="api/device/v1"/>');
});

it('authenticates a trusted entry like the admin entry: Sanctum sessions for the frontend', function (): void {
    $this->artisan('core:make-app', ['name' => 'portal'])->assertSuccessful();

    expect($this->project->read('app/Http/Portal/PortalServiceProvider.php'))->toContain(
        'use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;',
        "        Route::middlewareGroup(self::NAME, [\n            EnsureFrontendRequestsAreStateful::class,\n            EnsureUserIsEnabled::class,\n            ThrottleRequests::using(self::NAME),",
    )
        ->and($this->project->read('tests/Http/Portal/PortalServiceProviderTest.php'))
        ->toContain("it('requires authentication'", '$this->actingAs(User::factory()->create())', "->assertCookie(config()->string('session.cookie'))")
        ->not->toContain('Sanctum::actingAs');
});

it('authenticates a restricted entry with stateless Sanctum tokens only', function (): void {
    $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true])->assertSuccessful();

    expect($this->project->read('app/Http/Device/DeviceServiceProvider.php'))
        ->toContain("        Route::middlewareGroup(self::NAME, [\n            EnsureUserIsEnabled::class,\n            ThrottleRequests::using(self::NAME),")
        ->not->toContain('EnsureFrontendRequestsAreStateful')
        ->and($this->project->read('app/Http/Device/routes.php'))->toContain("Route::middleware('auth:sanctum')->group(")
        ->and($this->project->read('tests/Http/Device/DeviceServiceProviderTest.php'))
        ->toContain("it('requires authentication'", 'Sanctum::actingAs(User::factory()->create());', "->assertCookieMissing(config()->string('session.cookie'))");
});

it('derives the config key, directory and environment prefix from the name', function (): void {
    $this->artisan('core:make-app', ['name' => 'user-center'])->assertSuccessful();

    $config = require $this->project->path('config/core.php');

    expect($this->project->exists('app/Http/UserCenter/UserCenterServiceProvider.php'))->toBeTrue()
        ->and($config['apps']['user_center']['prefix'])->toBe('api/user-center')
        ->and($this->project->read('config/core.php'))->toContain("env('CORE_USER_CENTER_PREFIX', 'api/user-center')");
});

it('does not duplicate registrations when run again with --force', function (): void {
    $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true])->assertSuccessful();
    $this->project->put('app/Http/Device/routes.php', "<?php\n// edited\n");

    $this->artisan('core:make-app', ['name' => 'device', '--restricted' => true, '--force' => true])
        ->expectsOutputToContain('已登记过，跳过')
        ->assertSuccessful();

    expect($this->project->read('app/Http/Device/routes.php'))->not->toContain('// edited')
        ->and(substr_count($this->project->read('config/core.php'), "'device' => ["))->toBe(1)
        ->and(substr_count($this->project->read('bootstrap/providers.php'), 'DeviceServiceProvider::class'))->toBe(1)
        ->and(substr_count($this->project->read('phpunit.xml'), 'CORE_DEVICE_PREFIX'))->toBe(1)
        ->and($this->project->read('phpunit.xml'))->toContain('value="admin,device"');
});

it('fails without changing anything when the entry already exists', function (): void {
    $this->artisan('core:make-app', ['name' => 'portal'])->assertSuccessful();
    $this->project->put('app/Http/Portal/routes.php', "<?php\n// edited\n");

    $this->artisan('core:make-app', ['name' => 'portal'])
        ->expectsOutputToContain('App already exists.')
        ->assertFailed();

    expect($this->project->read('app/Http/Portal/routes.php'))->toContain('// edited');
});

it('asks for the name when it is missing', function (): void {
    $this->artisan('core:make-app')
        ->expectsQuestion('入口名？', 'portal')
        ->assertSuccessful();

    expect($this->project->exists('app/Http/Portal/PortalServiceProvider.php'))->toBeTrue();
});

it('rejects invalid names', function (string $name, string $message): void {
    $this->artisan('core:make-app', ['name' => $name])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect($this->project->files('app/Http'))->toBe(['app/Http/Admin/AdminServiceProvider.php', 'app/Http/Admin/Requests/ListRequest.php']);
})->with([
    'shared middleware directory' => ['middleware', '共用的中间件目录'],
    'subdirectory' => ['api/device', '不能包含子目录'],
    'starts with a digit' => ['1device', '不合法'],
    'reserved word' => ['class', '保留字'],
]);

it('prints the snippet to paste when a shared file has an unexpected format', function (): void {
    $this->project->put('config/core.php', "<?php\n\nreturn ['apps' => []];\n");

    $this->artisan('core:make-app', ['name' => 'portal'])
        ->expectsOutputToContain('无法在 [config/core.php] 中登记 入口 portal')
        ->expectsOutputToContain("        'portal' => [")
        ->assertFailed();

    expect($this->project->read('config/core.php'))->toBe("<?php\n\nreturn ['apps' => []];\n")
        ->and(require $this->project->path('bootstrap/providers.php'))->toContain('App\Http\Portal\PortalServiceProvider');
});
