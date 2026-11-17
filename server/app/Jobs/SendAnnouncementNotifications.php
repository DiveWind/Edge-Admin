<?php

namespace App\Jobs;

use App\Models\{Announcement, User};
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\{DeleteWhenMissingModels, WithoutRelations};
use Illuminate\Support\Facades\{Bus, Queue};

/**
 * 公告群发的编排任务：按 500 个启用用户一块，派发为一个批处理（Bus::batch，名称 `announcement:{id}`），
 * 每块一个 SendAnnouncementNotificationChunk，多个 worker 可并行处理，失败的块独立重试、互不影响。
 *
 * 在派发它的事务提交后才入队（ShouldQueueAfterCommit）；公告在执行前被删除时直接丢弃任务。
 * 队列由 AppServiceProvider 的 Queue::route() 集中指定（批处理不经过路由表，这里按同一张表取队列）。
 */
#[DeleteWhenMissingModels]
#[WithoutRelations]
class SendAnnouncementNotifications implements ShouldQueueAfterCommit
{
    use Queueable;

    protected const int CHUNK_SIZE = 500;

    public function __construct(public Announcement $announcement) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $chunks = [];

        User::query()->active()->select('id')->chunkById(self::CHUNK_SIZE, function (Collection $users) use (&$chunks): void {
            $chunks[] = new SendAnnouncementNotificationChunk($this->announcement, array_values(array_map(intval(...), $users->modelKeys())));
        });

        if ($chunks === []) {
            return;
        }

        Bus::batch($chunks)
            ->name("announcement:{$this->announcement->id}")
            ->allowFailures()
            ->onQueue(Queue::resolveQueueFromQueueRoute($chunks[0]))
            ->dispatch();
    }
}
