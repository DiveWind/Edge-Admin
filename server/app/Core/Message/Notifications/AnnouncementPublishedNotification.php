<?php

namespace App\Core\Message\Notifications;

use App\Core\Message\Data\NotificationContentData;
use App\Core\Message\Models\Announcement;

/**
 * 公告的站内通知（类型 `announcement_published`，设计文档 §6.9）：由 SendAnnouncementChunk 分批写入接收人的通知中心。
 *
 * - title：公告标题（纯文本）；content：正文的纯文本摘要（Announcement::excerpt()）；level：同公告；
 * - link：前端的公告详情路由（config('core.message.announcement.link')，`{id}` 替换为公告 id）；
 * - subject：公告——撤回时按它删除已发出的通知（RevokeAnnouncement）。
 */
class AnnouncementPublishedNotification extends InboxNotification
{
    public function __construct(public readonly Announcement $announcement) {}

    /**
     * 对外的类型名。
     */
    public static function type(): string
    {
        return 'announcement_published';
    }

    /**
     * 通知的内容。
     */
    public function toInbox(object $notifiable): NotificationContentData
    {
        $excerpt = $this->announcement->excerpt();

        return new NotificationContentData(
            title: $this->announcement->title,
            content: $excerpt === '' ? null : $excerpt,
            level: $this->announcement->level,
            link: str_replace('{id}', (string) $this->announcement->id, config()->string('core.message.announcement.link')),
            subject: $this->announcement,
        );
    }
}
