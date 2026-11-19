<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Actions\RevokeAnnouncement;
use App\Core\Message\Models\Announcement;
use App\Http\Admin\Message\Resources\AnnouncementResource;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 撤回公告（POST /message/announcements/{announcement}/revoke，设计文档 §6.9）：删除已发出的通知，返回公告。
 * 只有已发布的能撤回（AnnouncementPolicy::revoke，403；并发时 422）；撤回是终态。
 */
class RevokeAnnouncementController
{
    #[Authorize('revoke', 'announcement')]
    public function __invoke(Announcement $announcement, RevokeAnnouncement $action): AnnouncementResource
    {
        $announcement = $action->handle($announcement);

        return new AnnouncementResource($announcement->load(AnnouncementResource::relations(details: true)))->withDetails();
    }
}
