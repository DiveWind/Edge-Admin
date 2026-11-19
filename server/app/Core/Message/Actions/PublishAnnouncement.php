<?php

namespace App\Core\Message\Actions;

use App\Core\Message\Enums\AnnouncementStatus;
use App\Core\Message\Jobs\SendAnnouncement;
use App\Core\Message\Models\Announcement;
use App\Core\System\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * 发布公告（POST /message/announcements/{announcement}/publish，设计文档 §6.9）：立即返回，站内通知由队列分批发送。
 *
 * - 只有草稿能发布：AnnouncementPolicy 返回 403；两人同时发布时在事务中锁行复核，已不是草稿 → 422（message），因此只会发送一次；
 * - 状态改为 published，记下 published_at 与发布人 published_by；
 * - 派发 SendAnnouncement（ShouldQueueAfterCommit：事务提交后才入队），由它按批（core.message.announcements.batch_size）派发 Bus::batch。
 */
class PublishAnnouncement
{
    /**
     * Execute the action.
     *
     * @throws UnprocessableEntityHttpException
     */
    public function handle(Announcement $announcement, User $actor): Announcement
    {
        return DB::transaction(function () use ($announcement, $actor): Announcement {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);

            if (! $locked->isDraft()) {
                throw new UnprocessableEntityHttpException(__('Only draft announcements can be published.'));
            }

            $locked->status = AnnouncementStatus::Published;
            $locked->published_at = now();
            $locked->published_by = $actor->id;
            $locked->save();

            dispatch(new SendAnnouncement($locked->id));

            return $locked;
        });
    }
}
