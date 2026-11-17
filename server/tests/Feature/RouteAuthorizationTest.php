<?php

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\{Collection, Str};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 路由 ↔ 权限码
|--------------------------------------------------------------------------
|
| 功能权限以 #[Authorize('<权限码>')] 声明在控制器动作上（routes/api.php 只描述结构）。
| 这里把"每条受保护路由都有且只有一个功能权限、权限码都已声明"固化下来；
| 完整的对照表可用 `php artisan route:list -v --path=api` 查看（Authorize:<权限码>）。
|
*/

/**
 * 登录即可访问、不需要功能权限的路由（路由名，支持 * 通配）。
 */
const LOGIN_ONLY_ROUTES = [
    'auth.logout', 'auth.me', 'auth.menus', 'auth.profile', 'auth.password',
    'notifications.*', 'options.*', 'uploads.store',
];

/**
 * /api 下需要登录的路由 => 该路由的功能权限码（含 "." 的 ability；不含 "." 的是 Policy 方法）。
 *
 * @return Collection<string, list<string>>
 */
function protectedApiRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'api/')
            && in_array('auth:sanctum', $route->gatherMiddleware(), true))
        ->mapWithKeys(fn (RouteDefinition $route): array => [
            "{$route->methods()[0]} {$route->uri()} ({$route->getName()})" => collect($route->gatherMiddleware())
                ->filter(fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, Authorize::class.':'))
                ->map(fn (string $middleware): string => Str::of($middleware)->after(':')->before(',')->toString())
                ->filter(fn (string $ability): bool => str_contains($ability, '.'))
                ->values()
                ->all(),
        ]);
}

/**
 * config/permissions.php 中声明的全部操作权限码。
 *
 * @param  list<array<string, mixed>>  $nodes
 * @return list<string>
 */
function declaredActionCodes(array $nodes): array
{
    return collect($nodes)->flatMap(fn (array $node): array => [
        ...array_map(fn (string $action): string => "{$node['code']}.{$action}", array_keys($node['actions'] ?? [])),
        ...declaredActionCodes($node['children'] ?? []),
    ])->all();
}

test('every protected api route declares exactly one function permission unless it is login-only', function () {
    $routes = protectedApiRoutes();

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route => $codes) {
        $loginOnly = Str::is(LOGIN_ONLY_ROUTES, Str::between($route, '(', ')'));

        expect($codes)->toHaveCount($loginOnly ? 0 : 1, $loginOnly
            ? "{$route} 在登录即可访问的名单中，不应声明功能权限"
            : "{$route} 缺少功能权限：在控制器动作上添加 #[Authorize('<权限码>')]，或加入 LOGIN_ONLY_ROUTES");
    }
});

test('every permission code used by a route is declared in config/permissions.php', function () {
    $declared = declaredActionCodes(config('permissions'));

    expect(protectedApiRoutes()->flatten()->unique()->diff($declared)->values()->all())->toBe([]);
});
