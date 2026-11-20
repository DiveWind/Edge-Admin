<?php

use App\Core\Message\Actions\{PublishAnnouncement, RevokeAnnouncement};
use App\Core\Message\Jobs\{SendAnnouncement, SendAnnouncementChunk};
use App\Core\Message\Models\{Announcement, Notification};
use App\Core\System\Models\{Role, User};
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

/*
|--------------------------------------------------------------------------
| 公告发布后的分批发送（设计文档 §6.9）
|--------------------------------------------------------------------------
|
| 测试的队列驱动为 sync：发布后（事务提交后）SendAnnouncement 与批中的 SendAnnouncementChunk 都在请求中执行。
|
*/

/**
 * 收到公告通知的用户 id（按 id 排序）。
 *
 * @return list<int>
 */
function announcementRecipientIds(Announcement $announcement): array
{
    return Notification::query()->about($announcement)->where('type', 'announcement_published')
        ->orderBy('notifiable_id')->pluck('notifiable_id')->all();
}

describe('batches', function (): void {
    it('dispatches the job after publishing and returns at once', function (): void {
        Bus::fake();
        $announcement = Announcement::factory()->create();

        resolve(PublishAnnouncement::class)->handle($announcement, User::factory()->create());

        Bus::assertDispatched(SendAnnouncement::class, fn (SendAnnouncement $job): bool => $job->announcementId === $announcement->id);
        Bus::assertNothingBatched();
        expect(Notification::query()->count())->toBe(0);
    });

    it('splits the enabled recipients into batches of the configured size', function (): void {
        config(['core.message.announcement.batch_size' => 2]);
        $users = User::factory()->count(5)->create();
        User::factory()->disabled()->create();
        $announcement = Announcement::factory()->published()->create();
        $recipients = User::query()->where('status', 'enabled')->orderBy('id')->pluck('id')->all();
        Bus::fake();

        new SendAnnouncement($announcement->id)->handle();

        expect($recipients)->toContain(...$users->modelKeys());

        Bus::assertBatched(function (PendingBatch $batch) use ($announcement, $recipients): bool {
            $chunks = $batch->jobs->map(fn (SendAnnouncementChunk $job): array => [$job->announcementId, $job->userIds])->all();

            return $batch->name === "announcement:{$announcement->id}"
                && $batch->allowsFailures()
                && $chunks === array_map(fn (array $ids): array => [$announcement->id, $ids], array_chunk($recipients, 2));
        });
    });

    it('only batches the users with an enabled target role', function (): void {
        $target = Role::factory()->create();
        $disabledRole = Role::factory()->disabled()->create();
        $member = User::factory()->create()->assignRole($target);
        User::factory()->create()->assignRole($disabledRole);
        User::factory()->create();
        User::factory()->disabled()->create()->assignRole($target);
        $announcement = Announcement::factory()->published()->forRoles($target, $disabledRole)->create();
        Bus::fake();

        new SendAnnouncement($announcement->id)->handle();

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1 && $batch->jobs->first()->userIds === [$member->id]);
    });

    it('does not batch anything without recipients, or when the announcement is no longer published', function (Announcement $announcement): void {
        Bus::fake();

        new SendAnnouncement($announcement->id)->handle();

        Bus::assertNothingBatched();
    })->with([
        'no recipients' => fn (): Announcement => Announcement::factory()->published()->forRoles(Role::factory()->create())->create(),
        'revoked' => fn (): Announcement => Announcement::factory()->revoked()->create(),
    ]);
});

describe('delivery on the sync queue', function (): void {
    it('notifies the target users only, skipping disabled users and other roles', function (): void {
        config(['core.message.announcement.batch_size' => 1]);
        $target = Role::factory()->create();
        $first = User::factory()->create()->assignRole($target);
        $second = User::factory()->create()->assignRole($target);
        $other = User::factory()->create()->assignRole(Role::factory()->create());
        $disabled = User::factory()->disabled()->create()->assignRole($target);
        $announcement = Announcement::factory()->forRoles($target)->create([
            'title' => 'Maintenance',
            'content' => '<p>The system is <strong>down</strong> tonight.</p>',
            'level' => 'warning',
        ]);

        resolve(PublishAnnouncement::class)->handle($announcement, User::factory()->create());

        expect(announcementRecipientIds($announcement))->toBe([$first->id, $second->id])
            ->and(Notification::query()->whereIn('notifiable_id', [$other->id, $disabled->id])->count())->toBe(0)
            ->and(Notification::query()->where('notifiable_id', $first->id)->sole()->data)->toBeIgnoringKeyOrder([
                'title' => 'Maintenance',
                'content' => 'The system is down tonight.',
                'level' => 'warning',
                'link' => "/announcements/{$announcement->id}",
                'params' => [],
                'subject' => ['type' => 'announcement', 'id' => $announcement->id],
            ]);
    });

    it('notifies every enabled user when targeting all users', function (): void {
        $users = User::factory()->count(3)->create();
        User::factory()->disabled()->create();
        $publisher = User::factory()->create();
        $announcement = Announcement::factory()->create();

        resolve(PublishAnnouncement::class)->handle($announcement, $publisher);

        expect(announcementRecipientIds($announcement))->toBe(User::query()->where('status', 'enabled')->orderBy('id')->pluck('id')->all())
            ->and(announcementRecipientIds($announcement))->toContain(...$users->modelKeys(), ...[$publisher->id]);
    });

    it('uses the configured link', function (): void {
        config(['core.message.announcement.link' => '/notice/{id}/detail']);
        User::factory()->create();
        $announcement = Announcement::factory()->create();

        resolve(PublishAnnouncement::class)->handle($announcement, User::factory()->create());

        expect(Notification::query()->about($announcement)->first()?->data['link'])->toBe("/notice/{$announcement->id}/detail");
    });
});

describe('chunks', function (): void {
    it('re-checks the users when the chunk runs', function (): void {
        $announcement = Announcement::factory()->published()->create();
        $kept = User::factory()->create();
        $disabledLater = User::factory()->create();
        $deletedLater = User::factory()->create();
        $chunk = new SendAnnouncementChunk($announcement->id, [$kept->id, $disabledLater->id, $deletedLater->id]);

        $disabledLater->update(['status' => 'disabled']);
        $deletedLater->delete();
        $chunk->handle();

        expect(announcementRecipientIds($announcement))->toBe([$kept->id]);
    });

    it('sends nothing once the announcement is revoked', function (): void {
        $announcement = Announcement::factory()->published()->create();
        $user = User::factory()->create();
        $chunk = new SendAnnouncementChunk($announcement->id, [$user->id]);

        resolve(RevokeAnnouncement::class)->handle($announcement);
        $chunk->handle();

        expect(Notification::query()->count())->toBe(0);
    });

    it('sends nothing when the announcement is gone', function (): void {
        $user = User::factory()->create();

        new SendAnnouncementChunk(999_999, [$user->id])->handle();

        expect(Notification::query()->count())->toBe(0);
    });
});

it('does not notify a user twice when a chunk runs again after it was committed', function (): void {
    $users = User::factory()->count(2)->create();
    $announcement = Announcement::factory()->published()->create();
    $chunk = new SendAnnouncementChunk($announcement->id, $users->modelKeys());

    $chunk->handle();
    $chunk->handle();

    expect(announcementRecipientIds($announcement))->toBe($users->modelKeys());
});
