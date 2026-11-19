<?php

namespace App\Core\Message\Listeners;

use App\Core\Message\Notifications\ExportFinished as ExportFinishedNotification;
use App\Core\System\Events\ExportFinished;

/**
 * 导出完成或失败后通知发起人（设计文档 §6.10）：监听 System 的 ExportFinished 事件（在 MessageServiceProvider 中登记；System 不依赖 Message）。
 * 在导出的队列任务中同步执行；停用的用户不接收（InboxNotification::shouldSend()）。
 */
class NotifyExportFinished
{
    /**
     * Handle the event.
     */
    public function handle(ExportFinished $event): void
    {
        $user = $event->export->user()->first();

        $user?->notify(new ExportFinishedNotification($event->export));
    }
}
