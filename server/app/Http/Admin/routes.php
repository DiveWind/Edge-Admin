<?php

use App\Http\Admin\Monitor\Controllers\{LoginLogController, OnlineSessionController, OnlineTokenController, OperationLogController};
use App\Http\Admin\System\Controllers\{AuthCodesController, MenuAllController, MenuController, RoleController, RoleDataScopesController, RoleOptionsController, RolePermissionTreeController, RoleStatusController, UserController, UserInfoController, UserPasswordController, UserStatusController};
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

/*
|--------------------------------------------------------------------------
| 后台入口（Admin）路由
|--------------------------------------------------------------------------
|
| 由 AdminServiceProvider 加载：URL 前缀 config('core.apps.admin.prefix')，中间件组 admin，路由名前缀 admin.。
| 本文件只描述结构（分组导入各模块的控制器、apiResources）；授权写在控制器方法的 #[Authorize] 上（设计文档 §10.1）。
|
| 登录、登出、两步验证、找回密码、修改资料与密码由 Fortify 提供（同一前缀与中间件组，见 config/fortify.php）。
|
| 路由参数统一加格式约束（设计文档 §9）：模型 id 为数字（whereNumber），非法参数直接 404，
| 也避免 `system/roles/options` 这类固定路径被当成 id。
|
*/

// 登录前获取 CSRF Cookie（XSRF-TOKEN），之后的写请求带上 X-XSRF-TOKEN 请求头（Sanctum SPA 认证）
Route::get('sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])->name('sanctum.csrf-cookie');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('user/info', UserInfoController::class)->name('user.info');
    Route::get('auth/codes', AuthCodesController::class)->name('auth.codes');
    Route::get('menu/all', MenuAllController::class)->name('menu.all');

    // 系统管理（设计文档 §6.5）
    Route::prefix('system')->name('system.')->group(function (): void {
        Route::put('users/{user}/status', UserStatusController::class)->whereNumber('user')->name('users.status');
        Route::put('users/{user}/password', UserPasswordController::class)->whereNumber('user')->name('users.password');
        Route::apiResource('users', UserController::class)->whereNumber('user');

        Route::get('roles/options', RoleOptionsController::class)->name('roles.options');
        Route::get('roles/permission-tree', RolePermissionTreeController::class)->name('roles.permission-tree');
        Route::get('roles/data-scopes', RoleDataScopesController::class)->name('roles.data-scopes');
        Route::put('roles/{role}/status', RoleStatusController::class)->whereNumber('role')->name('roles.status');
        Route::apiResource('roles', RoleController::class)->whereNumber('role');

        Route::apiResource('menus', MenuController::class)->whereNumber('menu');
    });

    // 系统监控（设计文档 §6.6）：日志只读；在线会话的 {session} 是会话 ID 的前 16 位
    Route::prefix('monitor')->name('monitor.')->group(function (): void {
        Route::apiResource('operation-logs', OperationLogController::class)->only(['index', 'show'])->whereNumber('operation_log');
        Route::apiResource('login-logs', LoginLogController::class)->only(['index', 'show'])->whereNumber('login_log');

        Route::prefix('online')->name('online.')->group(function (): void {
            Route::apiResource('sessions', OnlineSessionController::class)->only(['index', 'destroy'])->where(['session' => '[A-Za-z0-9]{16}']);
            Route::apiResource('tokens', OnlineTokenController::class)->only(['index', 'destroy'])->whereNumber('token');
        });
    });
});
