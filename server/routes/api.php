<?php

use App\Http\Controllers\Api\Auth\PasswordController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Auth\TokenController;
use App\Http\Controllers\Api\Content\ArticleCategoryController;
use App\Http\Controllers\Api\Content\ArticleController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Message\AnnouncementController;
use App\Http\Controllers\Api\Message\NotificationController;
use App\Http\Controllers\Api\Message\PublishAnnouncementController;
use App\Http\Controllers\Api\Message\ReadAllNotificationsController;
use App\Http\Controllers\Api\Message\ReadNotificationController;
use App\Http\Controllers\Api\Message\UnreadNotificationCountController;
use App\Http\Controllers\Api\Monitor\ClearLoginLogsController;
use App\Http\Controllers\Api\Monitor\ClearOperationLogsController;
use App\Http\Controllers\Api\Monitor\FailedJobController;
use App\Http\Controllers\Api\Monitor\LoginLogController;
use App\Http\Controllers\Api\Monitor\OnlineSessionController;
use App\Http\Controllers\Api\Monitor\OperationLogController;
use App\Http\Controllers\Api\Monitor\RetryFailedJobController;
use App\Http\Controllers\Api\Monitor\RunScheduledTaskController;
use App\Http\Controllers\Api\Monitor\ScheduledTaskController;
use App\Http\Controllers\Api\Monitor\ScheduledTaskLogController;
use App\Http\Controllers\Api\Monitor\SystemInfoController;
use App\Http\Controllers\Api\Options\ArticleCategoryOptionController;
use App\Http\Controllers\Api\Options\DepartmentOptionController;
use App\Http\Controllers\Api\Options\DictOptionController;
use App\Http\Controllers\Api\Options\PermissionOptionController;
use App\Http\Controllers\Api\Options\PositionOptionController;
use App\Http\Controllers\Api\Options\RoleOptionController;
use App\Http\Controllers\Api\Options\SettingOptionController;
use App\Http\Controllers\Api\Options\UserOptionController;
use App\Http\Controllers\Api\System\AttachmentController;
use App\Http\Controllers\Api\System\DepartmentController;
use App\Http\Controllers\Api\System\DictItemController;
use App\Http\Controllers\Api\System\DictTypeController;
use App\Http\Controllers\Api\System\PermissionController;
use App\Http\Controllers\Api\System\PositionController;
use App\Http\Controllers\Api\System\RoleController;
use App\Http\Controllers\Api\System\SettingController;
use App\Http\Controllers\Api\System\UserController;
use App\Http\Controllers\Api\System\UserPasswordController;
use App\Http\Controllers\Api\System\UserStatusController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes（前缀 /api，契约见 docs/architecture.md）
|--------------------------------------------------------------------------
|
| 功能权限统一用官方 `can:<权限码>` 中间件声明；记录级规则（数据权限、
| 受保护对象等）在 Form Request / 控制器中通过 Policy 判定。
| 控制器只保留资源方法（index/show/store/update/destroy）或 __invoke，
| 非资源动作拆为单动作控制器（见 tests/Arch）。路由名是操作日志摘要的键。
|
| `// edge:routes` 为 `php artisan edge:make-module` 的插入锚点，请保留。
|
*/

Route::post('auth/login', [TokenController::class, 'store'])
    ->middleware('throttle:login')
    ->name('auth.login');

// 登录页需要的公开参数（站点名称等）
Route::get('options/settings', SettingOptionController::class)->name('options.settings');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    // 认证与个人中心
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('logout', [TokenController::class, 'destroy'])->name('logout');
        Route::get('me', [ProfileController::class, 'show'])->name('me');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile');
        Route::put('password', [PasswordController::class, 'update'])->name('password');
    });

    Route::get('dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

    // 系统管理
    Route::put('users/{user}/password', [UserPasswordController::class, 'update'])
        ->middleware('can:system.user.reset-password')
        ->name('users.password');
    Route::patch('users/{user}/status', [UserStatusController::class, 'update'])
        ->middleware('can:system.user.update')
        ->name('users.status');
    Route::apiResource('users', UserController::class)
        ->middlewareFor(['index', 'show'], 'can:system.user.view')
        ->middlewareFor('store', 'can:system.user.create')
        ->middlewareFor('update', 'can:system.user.update')
        ->middlewareFor('destroy', 'can:system.user.delete');

    Route::apiResource('roles', RoleController::class)
        ->middlewareFor(['index', 'show'], 'can:system.role.view')
        ->middlewareFor('store', 'can:system.role.create')
        ->middlewareFor('update', 'can:system.role.update')
        ->middlewareFor('destroy', 'can:system.role.delete');

    Route::get('permissions', PermissionController::class)
        ->middleware('can:system.permission.view')
        ->name('permissions.index');

    Route::apiResource('departments', DepartmentController::class)
        ->middlewareFor(['index', 'show'], 'can:system.department.view')
        ->middlewareFor('store', 'can:system.department.create')
        ->middlewareFor('update', 'can:system.department.update')
        ->middlewareFor('destroy', 'can:system.department.delete');

    Route::apiResource('positions', PositionController::class)
        ->middlewareFor(['index', 'show'], 'can:system.position.view')
        ->middlewareFor('store', 'can:system.position.create')
        ->middlewareFor('update', 'can:system.position.update')
        ->middlewareFor('destroy', 'can:system.position.delete');

    Route::apiResource('dict-types', DictTypeController::class)
        ->middlewareFor(['index', 'show'], 'can:system.dict.view')
        ->middlewareFor('store', 'can:system.dict.create')
        ->middlewareFor('update', 'can:system.dict.update')
        ->middlewareFor('destroy', 'can:system.dict.delete');

    Route::apiResource('dict-items', DictItemController::class)
        ->middlewareFor(['index', 'show'], 'can:system.dict.view')
        ->middlewareFor('store', 'can:system.dict.create')
        ->middlewareFor('update', 'can:system.dict.update')
        ->middlewareFor('destroy', 'can:system.dict.delete');

    Route::apiResource('settings', SettingController::class)
        ->middlewareFor(['index', 'show'], 'can:system.setting.view')
        ->middlewareFor('store', 'can:system.setting.create')
        ->middlewareFor('update', 'can:system.setting.update')
        ->middlewareFor('destroy', 'can:system.setting.delete');

    Route::apiResource('attachments', AttachmentController::class)
        ->only(['index', 'destroy'])
        ->middlewareFor('index', 'can:system.attachment.view')
        ->middlewareFor('destroy', 'can:system.attachment.delete');

    // 系统监控
    Route::delete('login-logs', ClearLoginLogsController::class)
        ->middleware('can:monitor.login-log.delete')
        ->name('login-logs.clear');
    Route::apiResource('login-logs', LoginLogController::class)
        ->only(['index', 'destroy'])
        ->middlewareFor('index', 'can:monitor.login-log.view')
        ->middlewareFor('destroy', 'can:monitor.login-log.delete');

    Route::delete('operation-logs', ClearOperationLogsController::class)
        ->middleware('can:monitor.operation-log.delete')
        ->name('operation-logs.clear');
    Route::apiResource('operation-logs', OperationLogController::class)
        ->only(['index', 'show', 'destroy'])
        ->middlewareFor(['index', 'show'], 'can:monitor.operation-log.view')
        ->middlewareFor('destroy', 'can:monitor.operation-log.delete');

    Route::apiResource('online-sessions', OnlineSessionController::class)
        ->only(['index', 'destroy'])
        ->middlewareFor('index', 'can:monitor.online-session.view')
        ->middlewareFor('destroy', 'can:monitor.online-session.delete');

    Route::get('scheduled-tasks', [ScheduledTaskController::class, 'index'])
        ->middleware('can:monitor.scheduled-task.view')
        ->name('scheduled-tasks.index');
    Route::post('scheduled-tasks/{key}/run', RunScheduledTaskController::class)
        ->middleware('can:monitor.scheduled-task.run')
        ->name('scheduled-tasks.run');
    Route::get('scheduled-task-logs', ScheduledTaskLogController::class)
        ->middleware('can:monitor.scheduled-task.view')
        ->name('scheduled-task-logs.index');

    Route::get('failed-jobs', [FailedJobController::class, 'index'])
        ->middleware('can:monitor.failed-job.view')
        ->name('failed-jobs.index');
    Route::post('failed-jobs/{uuid}/retry', RetryFailedJobController::class)
        ->middleware('can:monitor.failed-job.retry')
        ->name('failed-jobs.retry');
    Route::delete('failed-jobs/{uuid}', [FailedJobController::class, 'destroy'])
        ->middleware('can:monitor.failed-job.delete')
        ->name('failed-jobs.destroy');

    Route::get('system-info', SystemInfoController::class)
        ->middleware('can:monitor.system-info.view')
        ->name('system-info');

    // 消息
    Route::post('announcements/{announcement}/publish', PublishAnnouncementController::class)
        ->middleware('can:message.announcement.publish')
        ->name('announcements.publish');
    Route::apiResource('announcements', AnnouncementController::class)
        ->middlewareFor(['index', 'show'], 'can:message.announcement.view')
        ->middlewareFor('store', 'can:message.announcement.create')
        ->middlewareFor('update', 'can:message.announcement.update')
        ->middlewareFor('destroy', 'can:message.announcement.delete');

    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', UnreadNotificationCountController::class)->name('unread-count');
        Route::post('read-all', ReadAllNotificationsController::class)->name('read-all');
        Route::post('{id}/read', ReadNotificationController::class)->name('read');
        Route::delete('{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    // 内容（业务对接示例）
    Route::apiResource('article-categories', ArticleCategoryController::class)
        ->middlewareFor(['index', 'show'], 'can:content.category.view')
        ->middlewareFor('store', 'can:content.category.create')
        ->middlewareFor('update', 'can:content.category.update')
        ->middlewareFor('destroy', 'can:content.category.delete');

    Route::apiResource('articles', ArticleController::class)
        ->middlewareFor(['index', 'show'], 'can:content.article.view')
        ->middlewareFor('store', 'can:content.article.create')
        ->middlewareFor('update', 'can:content.article.update')
        ->middlewareFor('destroy', 'can:content.article.delete');

    // 生成器模块（php artisan edge:make-module）
    // edge:routes

    // 公共（登录即可访问）
    Route::prefix('options')->name('options.')->group(function (): void {
        Route::get('departments', DepartmentOptionController::class)->name('departments');
        Route::get('permissions', PermissionOptionController::class)->name('permissions');
        Route::get('roles', RoleOptionController::class)->name('roles');
        Route::get('positions', PositionOptionController::class)->name('positions');
        Route::get('article-categories', ArticleCategoryOptionController::class)->name('article-categories');
        Route::get('users', UserOptionController::class)->name('users');
        Route::get('dicts', DictOptionController::class)->name('dicts');
    });

    Route::post('uploads', UploadController::class)->name('uploads.store');
});
