<?php

namespace App\Http\Controllers\Api\Message;

use App\Actions\Message\PublishAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Support\Facades\Gate;

class PublishAnnouncementController extends Controller
{
    /**
     * 发布公告，并通过队列给所有启用用户发送站内通知。
     */
    public function __invoke(Announcement $announcement, PublishAnnouncement $publishAnnouncement): AnnouncementResource
    {
        Gate::authorize('publish', $announcement);

        $publishAnnouncement->handle($announcement);

        return new AnnouncementResource($announcement->load('creator'));
    }
}
