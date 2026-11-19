<?php

namespace App\Core\Message\Jobs;

use App\Core\Message\Models\Announcement;
use App\Core\System\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;

/**
 * 公告的分批发送（设计文档 §6.9）：由 PublishAnnouncement 派发（事务提交后才入队），在 worker 中执行，接口不等待。
 *
 * 公告仍是已发布时，按 chunkById 取接收人（Announcement::recipients()：启用的用户，目标为 roles 时另须拥有任一启用的目标角色）的 id，
 * 每 config('core.message.announcements.batch_size') 个一批，派发一个 Bus::batch（名称 `announcement:<id>`）：每批一个 SendAnnouncementChunk，
 * allowFailures()——一批失败不影响其他批，失败的批出现在失败任务中，可以单独重试。没有接收人时不派发。
 *
 * 只带公告 id（不序列化模型）：执行时公告已被删除则什么都不做。队列名不写在任务上（§10.1）。
 */
class SendAnnouncement implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly int $announcementId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $announcement = Announcement::query()->find($this->announcementId);

        if ($announcement === null || ! $announcement->isPublished()) {
            return;
        }

        $chunks = [];

        $announcement->recipients()->select('users.id')->chunkById(
            max(1, config()->integer('core.message.announcements.batch_size')),
            function (Collection $users) use (&$chunks): void {
                /** @var Collection<int, User> $users */
                $chunks[] = new SendAnnouncementChunk($this->announcementId, array_values(array_map(intval(...), $users->modelKeys())));
            },
            'users.id',
            'id',
        );

        if ($chunks === []) {
            return;
        }

        Bus::batch($chunks)
            ->name("announcement:{$this->announcementId}")
            ->allowFailures()
            ->dispatch();
    }
}
