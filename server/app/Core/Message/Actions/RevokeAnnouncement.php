<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Enums\AnnouncementStatus;
use App\Core\Message\Models\{Announcement, Notification};
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 撤回公告（POST /message/announcements/{announcement}/revoke，设计文档 §6.9）：撤回是终态（不能再发布、修改，只能删除）。
 *
 * - 只有已发布的能撤回：AnnouncementPolicy 返回 403；并发时锁行复核，已不是已发布 → 422（message）；
 * - 状态改为 revoked，记下 revoked_at，并在同一事务中删除已经发出的通知（Notification::about()，按 data->subject 查找）；
 * - 与分批发送互斥：SendAnnouncementChunk 在同一行锁下复核状态，撤回之后不会再写入通知。
 */
class RevokeAnnouncement
{
    /**
     * Execute the action.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function handle(Announcement $announcement): Announcement
    {
        return DB::transaction(function () use ($announcement): Announcement {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);

            if (! $locked->isPublished()) {
                throw new UnprocessableEntityHttpException(__('Only published announcements can be revoked.'));
            }

            $locked->status = AnnouncementStatus::Revoked;
            $locked->revoked_at = now();
            $locked->save();

            Notification::query()->about($locked)->delete();

            return $locked;
        });
    }
}
