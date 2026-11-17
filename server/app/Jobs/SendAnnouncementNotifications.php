<?php

namespace App\Jobs;

use App\Models\{Announcement, User};
use App\Notifications\AnnouncementPublished;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\Notification;

/**
 * 分块给所有启用用户发送公告的站内通知。
 *
 * 在派发它的事务提交后才入队（ShouldQueueAfterCommit）；公告在执行前被删除时直接丢弃任务。
 */
#[DeleteWhenMissingModels]
class SendAnnouncementNotifications implements ShouldQueueAfterCommit
{
    use Queueable;

    protected const int CHUNK_SIZE = 500;

    public function __construct(public Announcement $announcement) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $notification = new AnnouncementPublished($this->announcement);

        User::query()->active()->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($notification): void {
            Notification::sendNow($users, $notification);
        });
    }
}
