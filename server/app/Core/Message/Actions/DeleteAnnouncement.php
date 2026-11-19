<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 删除公告（DELETE /message/announcements/{announcement}，设计文档 §6.9）：草稿与已撤回的可以删除。
 *
 * - 已发布的由 AnnouncementPolicy 返回 403（先撤回）；并发时（另一人刚发布）锁行复核，已发布 → 422（message）；
 * - 目标角色随之删除（外键 cascadeOnDelete），附件连同文件删除（HasAttachments，事务提交后删文件）；
 *   已撤回的公告发出的通知在撤回时已删除，草稿没有发出过通知。
 */
class DeleteAnnouncement
{
    /**
     * Execute the action.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function handle(Announcement $announcement): void
    {
        DB::transaction(function () use ($announcement): void {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);

            if ($locked->isPublished()) {
                throw new UnprocessableEntityHttpException(__('Published announcements must be revoked before they can be deleted.'));
            }

            $locked->delete();
        });
    }
}
