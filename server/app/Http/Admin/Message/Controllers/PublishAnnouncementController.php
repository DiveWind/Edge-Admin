<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Actions\PublishAnnouncement;
use App\Core\Message\Models\Announcement;
use App\Core\System\Models\User;
use App\Http\Admin\Message\Resources\AnnouncementResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 发布公告（POST /message/announcements/{announcement}/publish，设计文档 §6.9）：立即返回公告，站内通知由队列分批发送。
 * 只有草稿能发布（AnnouncementPolicy::publish，403；并发时 422）。
 */
class PublishAnnouncementController
{
    #[Authorize('publish', 'announcement')]
    public function __invoke(Announcement $announcement, PublishAnnouncement $action, #[CurrentUser] User $actor): AnnouncementResource
    {
        $announcement = $action->handle($announcement, $actor);

        return new AnnouncementResource($announcement->load(AnnouncementResource::relations(details: true)))->withDetails();
    }
}
