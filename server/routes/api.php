<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Content\ArticleCategoryController;
use App\Http\Controllers\Api\Content\ArticleController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Message\AnnouncementController;
use App\Http\Controllers\Api\Message\NotificationController;
use App\Http\Controllers\Api\Monitor\FailedJobController;
use App\Http\Controllers\Api\Monitor\LoginLogController;
use App\Http\Controllers\Api\Monitor\OperationLogController;
use App\Http\Controllers\Api\Monitor\ScheduledTaskController;
use App\Http\Controllers\Api\Monitor\ScheduledTaskLogController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\System\DepartmentController;
use App\Http\Controllers\Api\System\PermissionController;
use App\Http\Controllers\Api\System\PositionController;
use App\Http\Controllers\Api\System\RoleController;
use App\Http\Controllers\Api\System\UserController;
use App\Http\Controllers\Api\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes（前缀 /api，契约见 docs/architecture.md）
|--------------------------------------------------------------------------
|
| 功能权限统一用官方 `can:<权限码>` 中间件声明；记录级规则（数据权限、
| 受保护对象等）在控制器中通过 Policy 判定。
|
*/

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    // 认证与个人中心
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile');
        Route::put('password', [ProfileController::class, 'password'])->name('password');
    });

    Route::get('dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

    // 系统管理
    Route::put('users/{user}/password', [UserController::class, 'resetPassword'])
        ->middleware('can:system.user.reset-password')
        ->name('users.password');
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

    // 系统监控
    Route::delete('login-logs', [LoginLogController::class, 'clear'])
        ->middleware('can:monitor.login-log.delete')
        ->name('login-logs.clear');
    Route::apiResource('login-logs', LoginLogController::class)
        ->only(['index', 'destroy'])
        ->middlewareFor('index', 'can:monitor.login-log.view')
        ->middlewareFor('destroy', 'can:monitor.login-log.delete');

    Route::delete('operation-logs', [OperationLogController::class, 'clear'])
        ->middleware('can:monitor.operation-log.delete')
        ->name('operation-logs.clear');
    Route::apiResource('operation-logs', OperationLogController::class)
        ->only(['index', 'show', 'destroy'])
        ->middlewareFor(['index', 'show'], 'can:monitor.operation-log.view')
        ->middlewareFor('destroy', 'can:monitor.operation-log.delete');

    Route::get('scheduled-tasks', [ScheduledTaskController::class, 'index'])
        ->middleware('can:monitor.scheduled-task.view')
        ->name('scheduled-tasks.index');
    Route::post('scheduled-tasks/{key}/run', [ScheduledTaskController::class, 'run'])
        ->middleware('can:monitor.scheduled-task.run')
        ->name('scheduled-tasks.run');
    Route::get('scheduled-task-logs', ScheduledTaskLogController::class)
        ->middleware('can:monitor.scheduled-task.view')
        ->name('scheduled-task-logs.index');

    Route::get('failed-jobs', [FailedJobController::class, 'index'])
        ->middleware('can:monitor.failed-job.view')
        ->name('failed-jobs.index');
    Route::post('failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry'])
        ->middleware('can:monitor.failed-job.retry')
        ->name('failed-jobs.retry');
    Route::delete('failed-jobs/{uuid}', [FailedJobController::class, 'destroy'])
        ->middleware('can:monitor.failed-job.delete')
        ->name('failed-jobs.destroy');

    // 消息
    Route::post('announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])
        ->middleware('can:message.announcement.publish')
        ->name('announcements.publish');
    Route::apiResource('announcements', AnnouncementController::class)
        ->middlewareFor(['index', 'show'], 'can:message.announcement.view')
        ->middlewareFor('store', 'can:message.announcement.create')
        ->middlewareFor('update', 'can:message.announcement.update')
        ->middlewareFor('destroy', 'can:message.announcement.delete');

    Route::prefix('notifications')->name('notifications.')->controller(NotificationController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('unread-count', 'unreadCount')->name('unread-count');
        Route::post('read-all', 'markAllAsRead')->name('read-all');
        Route::post('{id}/read', 'markAsRead')->name('read');
        Route::delete('{id}', 'destroy')->name('destroy');
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

    // 公共
    Route::prefix('options')->name('options.')->controller(OptionController::class)->group(function (): void {
        Route::get('departments', 'departments')->name('departments');
        Route::get('roles', 'roles')->name('roles');
        Route::get('positions', 'positions')->name('positions');
        Route::get('article-categories', 'articleCategories')->name('article-categories');
        Route::get('users', 'users')->name('users');
    });

    Route::post('uploads', UploadController::class)->name('uploads.store');
});
