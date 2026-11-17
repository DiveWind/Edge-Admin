<?php

namespace App\Actions\Message;

use App\Jobs\SendAnnouncementNotifications;
use App\Models\Announcement;

/**
 * 发布公告，并通过队列给所有启用用户发送站内通知。
 */
class PublishAnnouncement
{
    public function handle(Announcement $announcement): Announcement
    {
        $announcement->markAsPublished();

        SendAnnouncementNotifications::dispatch($announcement);

        return $announcement;
    }
}
