<?php

use App\Http\Admin\System\Controllers\{AuthCodesController, MenuAllController, UserInfoController};
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
*/

// 验证骨架用的示例路由，接入第一个模块后删除
Route::get('ping', fn () => response()->vben(['pong' => true]))->name('ping');

// 登录前获取 CSRF Cookie（XSRF-TOKEN），之后的写请求带上 X-XSRF-TOKEN 请求头（Sanctum SPA 认证）
Route::get('sanctum/csrf-cookie', [CsrfCookieController::class, 'show'])->name('sanctum.csrf-cookie');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('user/info', UserInfoController::class)->name('user.info');
    Route::get('auth/codes', AuthCodesController::class)->name('auth.codes');
    Route::get('menu/all', MenuAllController::class)->name('menu.all');
});
