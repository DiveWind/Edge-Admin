<?php

namespace App\Core\Message;

use App\Core\Message\Listeners\{DeleteNotificationsOfUser, NotifyExportFinished};
use App\Core\Message\Models\{Announcement, Notification};
use App\Core\System\Events\{ExportFinished, UserDeleting};
use App\Core\System\Services\{EnumRegistry, MenuRegistry};
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Message（内核模块）：职责与对外提供的能力见同目录的 README.md。
 *
 * 注册本模块的契约绑定、多态别名、命令、事件监听、语言包与视图，并通过 Core 的扩展点接入（设计文档 §4.3、§5.5）。
 * 多态别名与契约绑定由 core:make-model、core:make-contract 自动登记。
 *
 * - 节点（resources/menus.php，公告管理）注册到 System 的 MenuRegistry；通知级别与公告的枚举登记到 EnumRegistry（§6.7）；
 * - 用户被删除时删除他的通知（监听 System 的 Core 事件 UserDeleting，同一事务；System 不依赖 Message）；
 * - System 的导出完成或失败时通知发起人（监听 ExportFinished，设计文档 §6.10）。
 *
 * 已读通知的清理在 routes/console.php 中调度（model:prune）。
 */
class MessageServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(MenuRegistry $menus, EnumRegistry $enums): void
    {
        Relation::morphMap([
            'announcement' => Announcement::class,
            'notification' => Notification::class,
        ]);

        $menus->register(require __DIR__.'/resources/menus.php');
        $enums->register(require __DIR__.'/resources/enums.php');

        Event::listen(UserDeleting::class, DeleteNotificationsOfUser::class);
        Event::listen(ExportFinished::class, NotifyExportFinished::class);
    }
}
