<?php

use App\Http\Admin\AdminServiceProvider;
use App\Http\Admin\Content\Controllers\{ArticleController, ArticleExportController, ForceDeleteArticleController, PublishArticleController, RestoreArticleController, UnpublishArticleController};
use App\Http\Admin\Message\Controllers\{AnnouncementController, AnnouncementRoleOptionsController, NotificationController, PublishAnnouncementController, ReadAllNotificationsController, ReadNotificationController, ReceivedAnnouncementController, RevokeAnnouncementController, UnreadNotificationCountController};
use App\Http\Admin\Monitor\Controllers\{BackupController, BackupDestinationController, FailedJobController, FlushFailedJobsController, HealthResultController, LoginLogController, OnlineSessionController, OnlineTokenController, OperationLogController, RetryFailedJobController, RetryFailedJobsController, RunBackupController, RunHealthChecksController, RunScheduledTaskController, ScheduledTaskController, ScheduledTaskLogController, SystemInfoController};
use App\Http\Admin\System\Controllers\{AttachmentController, AttachmentDownloadController, AttachmentPreviewController, AuthCodesController, DictItemController, DictOptionsController, DictTypeController, EnumOptionsController, ExportController, MenuAllController, MenuController, PublicSettingsController, RoleController, RoleDataScopesController, RoleOptionsController, RolePermissionTreeController, RoleStatusController, SettingController, SettingGroupController, UserController, UserExportController, UserInfoController, UserPasswordController, UserStatusController};
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

// 附件的下载与预览（设计文档 §6.8）：签名的临时 URL（签发时已授权），不需要登录；过期或签名不对 403
Route::middleware('signed')->group(function (): void {
    Route::get('attachments/{attachment}/download', AttachmentDownloadController::class)->whereNumber('attachment')->name('attachments.download');
    Route::get('attachments/{attachment}/preview', AttachmentPreviewController::class)->whereNumber('attachment')->name('attachments.preview');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('user/info', UserInfoController::class)->name('user.info');
    Route::get('auth/codes', AuthCodesController::class)->name('auth.codes');
    Route::get('menu/all', MenuAllController::class)->name('menu.all');

    // 表单下拉的选项（设计文档 §6.7）：字典（启用项）与已登记的枚举，登录即可访问
    Route::get('options/dicts', DictOptionsController::class)->name('options.dicts');
    Route::get('options/enums', EnumOptionsController::class)->name('options.enums');

    // 附件（设计文档 §6.8）：上传只需登录，另经过上传限流（admin-uploads）；详情的访问权委托给绑定对象
    Route::post('attachments', [AttachmentController::class, 'store'])->middleware('throttle:'.AdminServiceProvider::UPLOAD_LIMITER)->name('attachments.store');
    Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment')->name('attachments.show');

    // 我的通知与我收到的公告（设计文档 §6.9）：个人功能，只能访问本人的通知、发给本人的公告
    Route::get('notifications/unread-count', UnreadNotificationCountController::class)->name('notifications.unread-count');
    Route::put('notifications/read-all', ReadAllNotificationsController::class)->name('notifications.read-all');
    Route::put('notifications/{notification}/read', ReadNotificationController::class)->whereUuid('notification')->name('notifications.read');
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'destroy'])->whereUuid('notification');
    Route::apiResource('announcements', ReceivedAnnouncementController::class)->only(['index', 'show'])->whereNumber('announcement');

    // 我的导出（设计文档 §6.10）：个人功能，只能访问本人发起的导出；文件的下载地址是附件的签名 URL
    Route::apiResource('exports', ExportController::class)->only(['index', 'show', 'destroy'])->whereNumber('export');

    // 系统管理（设计文档 §6.5）
    Route::prefix('system')->name('system.')->group(function (): void {
        Route::put('users/{user}/status', UserStatusController::class)->whereNumber('user')->name('users.status');
        Route::put('users/{user}/password', UserPasswordController::class)->whereNumber('user')->name('users.password');
        // 异步导出（§6.10）：筛选与排序同列表，另经过发起导出的限流（admin-exports）
        Route::post('users/export', UserExportController::class)->middleware('throttle:'.AdminServiceProvider::EXPORT_LIMITER)->name('users.export');
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

        // 附件管理（§6.8）：列表与删除；上传与详情在上面的 /attachments
        Route::apiResource('attachments', AttachmentController::class)->only(['index', 'destroy'])->whereNumber('attachment');
    });

    // 消息（设计文档 §6.9）：公告管理；发布后由队列分批发送站内通知，撤回时删除已发出的通知
    Route::prefix('message')->name('message.')->group(function (): void {
        Route::get('announcements/role-options', AnnouncementRoleOptionsController::class)->name('announcements.role-options');
        Route::post('announcements/{announcement}/publish', PublishAnnouncementController::class)->whereNumber('announcement')->name('announcements.publish');
        Route::post('announcements/{announcement}/revoke', RevokeAnnouncementController::class)->whereNumber('announcement')->name('announcements.revoke');
        Route::apiResource('announcements', AnnouncementController::class)->whereNumber('announcement');
    });

    // 业务模块示范：文章（设计文档 §6.11，删除 Content 模块时连同本组删除）；软删除整套（§10.1）：
    // 默认的路由绑定不含已删除的文章，恢复与彻底删除的路由 withTrashed()；导出另经过发起导出的限流（admin-exports）
    Route::prefix('content')->name('content.')->group(function (): void {
        Route::post('articles/export', ArticleExportController::class)->middleware('throttle:'.AdminServiceProvider::EXPORT_LIMITER)->name('articles.export');
        Route::post('articles/{article}/publish', PublishArticleController::class)->whereNumber('article')->name('articles.publish');
        Route::post('articles/{article}/unpublish', UnpublishArticleController::class)->whereNumber('article')->name('articles.unpublish');
        Route::post('articles/{article}/restore', RestoreArticleController::class)->whereNumber('article')->withTrashed()->name('articles.restore');
        Route::delete('articles/{article}/force-delete', ForceDeleteArticleController::class)->whereNumber('article')->withTrashed()->name('articles.force-delete');
        Route::apiResource('articles', ArticleController::class)->whereNumber('article');
    });

    // 系统监控（设计文档 §6.6）：日志只读；在线会话的 {session} 是会话 ID 的前 16 位，失败任务的 {failed_job} 是 UUID；
    // 立即执行、重试、立即检查、立即备份另经过运维动作的限流（admin-ops）
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

        // 备份（§6.10）：备份列表、各备份盘的健康状态、立即备份（派发调度中的 backup:run）；不提供下载
        Route::get('backups/destinations', BackupDestinationController::class)->name('backups.destinations');
        Route::post('backups/run', RunBackupController::class)->middleware($ops)->name('backups.run');
        Route::apiResource('backups', BackupController::class)->only(['index']);
    });
});
