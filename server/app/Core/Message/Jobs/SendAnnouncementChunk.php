<?php

namespace App\Core\Message\Jobs;

use App\Core\Message\Models\{Announcement, Notification as StoredNotification};
use App\Core\Message\Notifications\AnnouncementPublishedNotification;
use App\Core\System\Models\User;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\{Backoff, Tries};
use Illuminate\Support\Facades\{DB, Notification};

/**
 * 公告站内通知的一批（批处理 `announcement:<id>` 中的一个任务，由 SendAnnouncement 派发；设计文档 §6.9）。
 *
 * - 批次已取消时跳过；
 * - 在事务中锁住公告行、复核仍是已发布（与 RevokeAnnouncement 用同一行锁互斥：撤回之后不会再写入），
 *   再按 Announcement::recipients() 重新查询这一批的用户——入队后被停用、删除、移出目标角色的不再收到；
 * - Notification::sendNow() 写入 AnnouncementPublishedNotification（database 通道，InboxNotification 另对停用用户不发送）；
 * - 整批在一个事务中：失败时回滚，重试不会重复发送（最多 3 次）；提交之后才失败的重试（worker 在确认任务前崩溃、
 *   在“失败任务”中重试 SendAnnouncement）跳过已经收到这条公告的用户（按 Notification::about() 与本批的用户 id 查找）。
 */
#[Tries(3)]
#[Backoff([10, 60])]
class SendAnnouncementChunk implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * @param  list<int>  $userIds  这一批的用户 id
     */
    public function __construct(public readonly int $announcementId, public readonly array $userIds) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled() === true) {
            return;
        }

        DB::transaction(function (): void {
            $announcement = Announcement::query()->lockForUpdate()->find($this->announcementId);

            if ($announcement === null || ! $announcement->isPublished()) {
                return;
            }

            // 重试（worker 在提交之后、确认任务之前崩溃，或在“失败任务”中重试整批）时跳过已经收到这条公告的用户
            $notified = StoredNotification::query()->about($announcement)
                ->where('notifiable_type', (new User)->getMorphClass())
                ->whereIn('notifiable_id', $this->userIds)
                ->pluck('notifiable_id')
                ->all();

            $users = $announcement->recipients()->whereKey($this->userIds)->whereKeyNot($notified)->get();

            Notification::sendNow($users, new AnnouncementPublishedNotification($announcement));
        });
    }
}
