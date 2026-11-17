<?php

use App\Http\Controllers\Api\Auth\{MenuController, PasswordController, ProfileController, TokenController};
use App\Http\Controllers\Api\Content\{ArticleCategoryController, ArticleController};
use App\Http\Controllers\Api\{DashboardController, UploadController};
use App\Http\Controllers\Api\Message\{AnnouncementController, NotificationController, PublishAnnouncementController, ReadAllNotificationsController, ReadNotificationController, UnreadNotificationCountController};
use App\Http\Controllers\Api\Monitor\{ClearLoginLogsController, ClearOperationLogsController, FailedJobController, LoginLogController, OnlineSessionController, OperationLogController, RetryFailedJobController, RunScheduledTaskController, ScheduledTaskController, ScheduledTaskLogController, SystemInfoController};
use App\Http\Controllers\Api\Options\{ArticleCategoryOptionController, DepartmentOptionController, DictOptionController, PermissionOptionController, PositionOptionController, RoleOptionController, SettingOptionController, UserOptionController};
use App\Http\Controllers\Api\System\{AttachmentController, DepartmentController, DictItemController, DictTypeController, PermissionController, PositionController, RoleController, SettingController, UserController, UserPasswordController, UserStatusController};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes（前缀 /api，契约见 docs/architecture.md）
|--------------------------------------------------------------------------
|
| 本文件只描述 URL 结构与路由名（路由名即操作日志摘要的键，见 lang/{locale}/operations.php）。
| 授权声明在控制器动作上，与实现放在一起：
|   - 功能权限：#[Authorize('<权限码>')]（权限码在 config/permissions.php 声明）；
|   - 记录级规则：#[Authorize('<policy 方法>', '<路由参数>')]（数据权限、受保护对象等）。
| tests/Feature/RouteAuthorizationTest.php 保证 auth:sanctum 下的每条路由都有功能权限
| （登录即可访问的少数路由在该测试中显式列出），且权限码都已在 config/permissions.php 中声明。
|
| `// edge:resources`、`// edge:options` 为 `php artisan edge:make-module` 的插入锚点，请保留。
|
*/

Route::post('auth/login', [TokenController::class, 'store'])->name('auth.login');

// 登录页需要的公开参数（站点名称等）
Route::get('options/settings', SettingOptionController::class)->name('options.settings');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    // 认证与个人中心（登录即可访问）
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('logout', [TokenController::class, 'destroy'])->name('logout');
        Route::get('menus', MenuController::class)->name('menus');
        Route::put('password', [PasswordController::class, 'update'])->name('password');

        Route::controller(ProfileController::class)->group(function (): void {
            Route::get('me', 'show')->name('me');
            Route::put('profile', 'update')->name('profile');
        });
    });

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('uploads', UploadController::class)->name('uploads.store');

    // 系统管理
    Route::apiResources([
        'users' => UserController::class,
        'roles' => RoleController::class,
        'permissions' => PermissionController::class,
        'departments' => DepartmentController::class,
        'positions' => PositionController::class,
        'dict-types' => DictTypeController::class,
        'dict-items' => DictItemController::class,
        'settings' => SettingController::class,
    ]);
    Route::apiResource('attachments', AttachmentController::class)->only(['index', 'destroy']);
    Route::put('users/{user}/password', [UserPasswordController::class, 'update'])->name('users.password');
    Route::patch('users/{user}/status', [UserStatusController::class, 'update'])->name('users.status');

    // 系统监控
    Route::apiResource('login-logs', LoginLogController::class)->only(['index', 'destroy']);
    Route::delete('login-logs', ClearLoginLogsController::class)->name('login-logs.clear');
    Route::apiResource('operation-logs', OperationLogController::class)->only(['index', 'show', 'destroy']);
    Route::delete('operation-logs', ClearOperationLogsController::class)->name('operation-logs.clear');
    Route::apiResource('online-sessions', OnlineSessionController::class)->only(['index', 'destroy']);
    Route::apiResource('scheduled-tasks', ScheduledTaskController::class)->only(['index']);
    Route::post('scheduled-tasks/{key}/run', RunScheduledTaskController::class)->name('scheduled-tasks.run');
    Route::get('scheduled-task-logs', ScheduledTaskLogController::class)->name('scheduled-task-logs.index');
    Route::apiResource('failed-jobs', FailedJobController::class)->only(['index', 'destroy'])->parameters(['failed-jobs' => 'uuid']);
    Route::post('failed-jobs/{uuid}/retry', RetryFailedJobController::class)->name('failed-jobs.retry');
    Route::get('system-info', SystemInfoController::class)->name('system-info');

    // 消息
    Route::apiResource('announcements', AnnouncementController::class);
    Route::post('announcements/{announcement}/publish', PublishAnnouncementController::class)->name('announcements.publish');

    Route::apiResource('notifications', NotificationController::class)->only(['index', 'destroy'])->parameters(['notifications' => 'id']);
    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('unread-count', UnreadNotificationCountController::class)->name('unread-count');
        Route::post('read-all', ReadAllNotificationsController::class)->name('read-all');
        Route::post('{id}/read', ReadNotificationController::class)->name('read');
    });

    // 业务模块（内容管理为对接示例；edge:make-module 生成的模块登记在这里）
    Route::apiResources([
        'article-categories' => ArticleCategoryController::class,
        'articles' => ArticleController::class,
        // edge:resources
    ]);

    // 下拉选项（登录即可访问）
    Route::prefix('options')->name('options.')->group(function (): void {
        Route::get('departments', DepartmentOptionController::class)->name('departments');
        Route::get('permissions', PermissionOptionController::class)->name('permissions');
        Route::get('roles', RoleOptionController::class)->name('roles');
        Route::get('positions', PositionOptionController::class)->name('positions');
        Route::get('article-categories', ArticleCategoryOptionController::class)->name('article-categories');
        Route::get('users', UserOptionController::class)->name('users');
        Route::get('dicts', DictOptionController::class)->name('dicts');
        // edge:options
    });
});
