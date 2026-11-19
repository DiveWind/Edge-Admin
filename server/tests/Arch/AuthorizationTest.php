<?php

use App\Core\System\Contracts\MenuRegistry;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\{Gate, Route};
use Illuminate\Support\Str;

require_once __DIR__.'/helpers.php';

/*
|--------------------------------------------------------------------------
| 授权约定（设计文档 §8.1、§8.3、§10.5）
|--------------------------------------------------------------------------
|
| - 每条受保护（需要登录）的路由都有授权声明：控制器方法上的 #[Authorize]（或 can 中间件）；
|   只需登录的路由显式列在 LOGIN_ONLY_ROUTES 中；
| - 代码里用到的权限码（含 "." 的 ability）都已在 MenuRegistry 中声明；
| - 不使用 spatie 的判定 API 与 role / permission 中间件（它们不看节点与角色的停用状态）。
|   “不调用判定 API”同时由 PHPStan（phpstan.neon 的 disallowedMethodCalls）检查；
| - 每个模型都有 Policy，且标准方法齐全（§8.3）。
|
*/

/**
 * 只需登录、不需要授权声明的路由（路由名，支持 * 通配）。
 */
const LOGIN_ONLY_ROUTES = [
    // 后台入口：当前用户自己的信息、权限码与菜单（§6.2）
    'admin.user.info', 'admin.auth.codes', 'admin.menu.all',
    // Fortify：登出、确认密码、修改自己的资料与密码、两步验证（§7.2）
    'logout', 'password.confirmation', 'password.confirm.store',
    'user-profile-information.update', 'user-password.update', 'two-factor.*',
];

/**
 * Policy 的标准方法（§8.3）。
 */
const POLICY_METHODS = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'deleteAny', 'restoreAny', 'forceDeleteAny'];

/**
 * spatie 的判定 API（§8.1）。
 */
const SPATIE_CHECKS = [
    'hasRole', 'hasAnyRole', 'hasAllRoles', 'hasExactRoles',
    'hasPermissionTo', 'checkPermissionTo', 'hasAnyPermission', 'hasAllPermissions',
    'hasDirectPermission', 'hasAnyDirectPermission', 'hasAllDirectPermissions',
    'getAllPermissions', 'getPermissionsViaRoles',
];

/**
 * 以 ability 为第一个参数的调用：Gate / 用户模型 / 控制器的授权方法，以及 #[Authorize]。
 */
const ABILITY_CALLS = ['can', 'cannot', 'cant', 'allows', 'denies', 'authorize', 'inspect', 'check', 'any', 'none', 'Authorize'];

/**
 * 路由的描述（方法、URI、路由名）。
 */
function routeLabel(RouteDefinition $route): string
{
    return implode('|', $route->methods())." /{$route->uri()} (".($route->getName() ?? '未命名').')';
}

/**
 * 路由上的授权声明：#[Authorize] 与 can 中间件的 ability。
 *
 * @return list<string>
 */
function routeAbilities(RouteDefinition $route): array
{
    return collect($route->gatherMiddleware())
        ->filter(fn (mixed $middleware): bool => is_string($middleware) && (str_starts_with($middleware, Authorize::class.':') || str_starts_with($middleware, 'can:')))
        ->map(fn (string $middleware): string => Str::of($middleware)->after(':')->before(',')->toString())
        ->values()
        ->all();
}

test('每条受保护的路由都有授权声明，只需登录的路由列在 LOGIN_ONLY_ROUTES 中', function (): void {
    $protected = collect(Route::getRoutes()->getRoutes())->filter(fn (RouteDefinition $route): bool => collect($route->gatherMiddleware())
        ->contains(fn (mixed $middleware): bool => is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:'))));

    expect($protected)->not->toBeEmpty();

    foreach ($protected as $route) {
        if (Str::is(LOGIN_ONLY_ROUTES, (string) $route->getName())) {
            expect(routeAbilities($route))->toBe([], routeLabel($route).' 在只需登录的名单中，不应再声明授权');
        } else {
            expect(routeAbilities($route))->not->toBeEmpty(routeLabel($route).' 缺少授权声明：在控制器方法上添加 #[Authorize(...)]，或（只需登录时）加入 LOGIN_ONLY_ROUTES');
        }
    }
});

test('每个模型都有 Policy，且标准方法齐全', function (): void {
    $models = array_values(array_filter(archClasses(), fn (string $class): bool => is_subclass_of($class, Model::class) && ! new ReflectionClass($class)->isAbstract()));

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        $policy = Gate::getPolicyFor($model);

        expect($policy)->not->toBeNull("{$model} 缺少 Policy：执行 php artisan core:make-policy（设计文档 §8.3）");

        $missing = array_values(array_filter(POLICY_METHODS, fn (string $method): bool => ! method_exists($policy, $method)));

        expect($missing)->toBe([], $model.' 的 Policy 缺少标准方法');
    }
});

test('路由不使用 spatie 的 role / permission 中间件', function (): void {
    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach ($route->gatherMiddleware() as $middleware) {
            expect(is_string($middleware) && preg_match('/^(role|permission|role_or_permission):/', $middleware) === 1)
                ->toBeFalse(routeLabel($route)." 使用了 {$middleware}：授权一律走 #[Authorize] / Gate / Policy（设计文档 §8.1）");
        }
    }
});

test('代码与路由中用到的权限码都已在 MenuRegistry 中声明', function (): void {
    $menus = resolve(MenuRegistry::class);
    $undeclared = [];

    foreach (archPhpFiles(['app']) as $file) {
        foreach (archStringArgumentCalls($file->getContents(), ABILITY_CALLS) as $call) {
            if (str_contains($call['argument'], '.') && ! $menus->has($call['argument'])) {
                $undeclared[] = "{$file->getRelativePathname()}:{$call['line']} {$call['name']}('{$call['argument']}')";
            }
        }
    }

    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach (routeAbilities($route) as $ability) {
            if (str_contains($ability, '.') && ! $menus->has($ability)) {
                $undeclared[] = routeLabel($route)." {$ability}";
            }
        }
    }

    expect($undeclared)->toBe([], '权限码未声明：在模块的 resources/menus.php 中声明（设计文档 §8.2）');
});

test('权限码检查能识别各种写法', function (): void {
    $code = <<<'PHP'
        <?php
        #[Authorize('system.user')]
        function demo($user) {
            $user->can('system.user.create');
            Gate::allows("system.role");
            $user->can($ability);
            $user->update(['name' => 'x.y']);
        }
        PHP;

    expect(array_column(archStringArgumentCalls($code, ABILITY_CALLS), 'argument'))
        ->toBe(['system.user', 'system.user.create', 'system.role']);
});

test('app/ 中不调用 spatie 的判定 API', function (): void {
    $violations = [];

    foreach (archPhpFiles(['app']) as $file) {
        $tokens = array_values(array_filter(PhpToken::tokenize($file->getContents()), fn (PhpToken $token): bool => ! $token->isIgnorable()));

        foreach ($tokens as $index => $token) {
            if ($token->is(T_STRING) && in_array($token->text, SPATIE_CHECKS, true)
                && ($tokens[$index - 1] ?? null)?->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON])) {
                $violations[] = "{$file->getRelativePathname()}:{$token->line} {$token->text}()";
            }
        }
    }

    expect($violations)->toBe([], '授权只走 can() / Gate / Policy，是否超管用 isSuperAdmin()（设计文档 §8.1）');
});
