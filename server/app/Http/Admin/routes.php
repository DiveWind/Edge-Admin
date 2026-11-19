<?php

use App\Http\Admin\AdminServiceProvider;
use App\Http\Admin\Monitor\Controllers\{FailedJobController, FlushFailedJobsController, HealthResultController, LoginLogController, OnlineSessionController, OnlineTokenController, OperationLogController, RetryFailedJobController, RetryFailedJobsController, RunHealthChecksController, RunScheduledTaskController, ScheduledTaskController, ScheduledTaskLogController, SystemInfoController};
use App\Http\Admin\System\Controllers\{AuthCodesController, DictItemController, DictOptionsController, DictTypeController, EnumOptionsController, MenuAllController, MenuController, PublicSettingsController, RoleController, RoleDataScopesController, RoleOptionsController, RolePermissionTreeController, RoleStatusController, SettingController, SettingGroupController, UserController, UserInfoController, UserPasswordController, UserStatusController};
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

// 公开参数（设计文档 §6.7）：不需要登录，只输出声明为 public 的系统参数（登录页的站点名称、提示文案等）
Route::get('settings/public', PublicSettingsController::class)->name('settings.public');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('user/info', UserInfoController::class)->name('user.info');
    Route::get('auth/codes', AuthCodesController::class)->name('auth.codes');
    Route::get('menu/all', MenuAllController::class)->name('menu.all');

    // 表单下拉的选项（设计文档 §6.7）：字典（启用项）与已登记的枚举，登录即可访问
    Route::get('options/dicts', DictOptionsController::class)->name('options.dicts');
    Route::get('options/enums', EnumOptionsController::class)->name('options.enums');

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

        // 字典（§6.7）：字典项的列表与新增挂在类型下，修改与删除按字典项的 id
        Route::get('dict-types/{dict_type}/items', [DictItemController::class, 'index'])->whereNumber('dict_type')->name('dict-types.items.index');
        Route::post('dict-types/{dict_type}/items', [DictItemController::class, 'store'])->whereNumber('dict_type')->name('dict-types.items.store');
        Route::apiResource('dict-types', DictTypeController::class)->whereNumber('dict_type');
        Route::apiResource('dict-items', DictItemController::class)->only(['update', 'destroy'])->whereNumber('dict_item');

        // 系统参数（§6.7）：{setting} 是参数的键、{setting_group} 是分组，由 AdminServiceProvider 的 Route::bind 解析为已声明的参数（未声明 404）
        Route::apiResource('settings', SettingController::class)->only(['index', 'update'])
            ->where(['setting' => '[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*']);
        Route::apiResource('setting-groups', SettingGroupController::class)->only(['update'])
            ->where(['setting_group' => '[a-z][a-z0-9_]*']);
    });

    // 系统监控（设计文档 §6.6）：日志只读；在线会话的 {session} 是会话 ID 的前 16 位，失败任务的 {failed_job} 是 UUID；
    // 立即执行、重试、立即检查另经过运维动作的限流（admin-ops）
    Route::prefix('monitor')->name('monitor.')->group(function (): void {
        $ops = 'throttle:'.AdminServiceProvider::OPS_LIMITER;

        Route::apiResource('operation-logs', OperationLogController::class)->only(['index', 'show'])->whereNumber('operation_log');
        Route::apiResource('login-logs', LoginLogController::class)->only(['index', 'show'])->whereNumber('login_log');

        Route::prefix('online')->name('online.')->group(function (): void {
            Route::apiResource('sessions', OnlineSessionController::class)->only(['index', 'destroy'])->where(['session' => '[A-Za-z0-9]{16}']);
            Route::apiResource('tokens', OnlineTokenController::class)->only(['index', 'destroy'])->whereNumber('token');
        });

        Route::get('schedules/{schedule}/logs', ScheduledTaskLogController::class)->whereNumber('schedule')->name('schedules.logs');
        Route::post('schedules/{schedule}/run', RunScheduledTaskController::class)->whereNumber('schedule')->middleware($ops)->name('schedules.run');
        Route::apiResource('schedules', ScheduledTaskController::class)->only(['index']);

        Route::post('failed-jobs/retry', RetryFailedJobsController::class)->middleware($ops)->name('failed-jobs.retry-all');
        Route::post('failed-jobs/{failed_job}/retry', RetryFailedJobController::class)->whereUuid('failed_job')->middleware($ops)->name('failed-jobs.retry');
        Route::delete('failed-jobs', FlushFailedJobsController::class)->name('failed-jobs.flush');
        Route::apiResource('failed-jobs', FailedJobController::class)->only(['index', 'show', 'destroy'])->whereUuid('failed_job');

        Route::get('health', HealthResultController::class)->name('health');
        Route::post('health/check', RunHealthChecksController::class)->middleware($ops)->name('health.check');
        Route::get('system-info', SystemInfoController::class)->name('system-info');
    });
});
