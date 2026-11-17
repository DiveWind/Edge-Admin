<?php

namespace App\Jobs;

use App\Models\{Announcement, User};
use App\Notifications\AnnouncementPublished;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\{Backoff, DeleteWhenMissingModels, Tries, WithoutRelations};
use Illuminate\Support\Facades\Notification;

/**
 * 公告站内通知的一个分块（批处理 `announcement:{id}` 中的一个任务，由 SendAnnouncementNotifications 编排）。
 *
 * 分块独立重试：某一块失败只重发这一块（最多 3 次），不会让已发送的其他块重复发送；
 * 执行时再过滤一次启用状态（入队后被禁用的用户不再收到）。
 */
#[Tries(3)]
#[Backoff([10, 60])]
#[DeleteWhenMissingModels]
#[WithoutRelations]
class SendAnnouncementNotificationChunk implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(public Announcement $announcement, public array $userIds) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        Notification::sendNow(
            User::query()->active()->whereKey($this->userIds)->get(),
            new AnnouncementPublished($this->announcement),
        );
    }
}
