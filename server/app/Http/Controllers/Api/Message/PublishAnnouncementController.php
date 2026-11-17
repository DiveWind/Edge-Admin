<?php

namespace App\Http\Controllers\Api\Message;

use App\Actions\Message\PublishAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class PublishAnnouncementController extends Controller
{
    /**
     * 发布公告，并通过队列给所有启用用户发送站内通知。
     */
    #[Authorize('message.announcement.publish')]
    #[Authorize('publish', 'announcement')]
    public function __invoke(Announcement $announcement, PublishAnnouncement $publishAnnouncement): AnnouncementResource
    {
        $publishAnnouncement->handle($announcement);

        return new AnnouncementResource($announcement->load('creator'));
    }
}
