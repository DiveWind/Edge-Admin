<?php

use App\Core\Message\Actions\PublishAnnouncement;
use App\Core\Message\Jobs\SendAnnouncement;
use App\Core\Message\Models\{Announcement, Notification};
use App\Core\System\Models\User;
use App\Support\Exceptions\BusinessRuleViolation;
use Illuminate\Support\Facades\Bus;

/*
|--------------------------------------------------------------------------
| 发布公告：POST /message/announcements/{announcement}/publish（设计文档 §6.9）
|--------------------------------------------------------------------------
|
| 分批发送的组成与接收人规则见 tests/Core/Message/Jobs/SendAnnouncementTest.php。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('returns 401 for guests', function (): void {
    $announcement = Announcement::factory()->create();

    $this->postJson("/api/admin/message/announcements/{$announcement->id}/publish")->assertUnauthorized();
});

it('returns 403 without the message.announcement.publish code', function (): void {
    $announcement = Announcement::factory()->create();

    $this->actingAs(userWithDataScope('all', 'message.announcement', 'message.announcement.update'))
        ->postJson("/api/admin/message/announcements/{$announcement->id}/publish")
        ->assertForbidden();

    expect($announcement->refresh()->isDraft())->toBeTrue();
});

it('returns 403 outside the data scope', function (): void {
    $announcement = Announcement::factory()->create();

    $this->actingAs(userWithPermissions('message.announcement.publish'))
        ->postJson("/api/admin/message/announcements/{$announcement->id}/publish")
        ->assertForbidden();
});

it('returns 403 for announcements that are not drafts', function (string $state): void {
    $announcement = Announcement::factory()->{$state}()->create();
    Bus::fake();

    $this->actingAs(superAdminActor())
        ->postJson("/api/admin/message/announcements/{$announcement->id}/publish")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only draft announcements can be published.');

    Bus::assertNotDispatched(SendAnnouncement::class);
})->with(['published', 'revoked']);

it('publishes the draft at once and queues the notifications', function (): void {
    $actor = userWithPermissions('message.announcement.publish');
    $announcement = Announcement::factory()->create(['created_by' => $actor->id]);
    Bus::fake();

    $this->actingAs($actor)
        ->postJson("/api/admin/message/announcements/{$announcement->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.published_at', now()->toIso8601String())
        ->assertJsonPath('data.publisher', ['id' => $actor->id, 'username' => $actor->username, 'name' => $actor->name])
        ->assertJsonPath('data.content', $announcement->content);

    expect($announcement->refresh())
        ->isPublished()->toBeTrue()
        ->published_by->toBe($actor->id);

    Bus::assertDispatched(SendAnnouncement::class, fn (SendAnnouncement $job): bool => $job->announcementId === $announcement->id);
});

it('sends the notifications on the sync queue', function (): void {
    $recipient = User::factory()->create();
    $announcement = Announcement::factory()->create();

    $this->actingAs(superAdminActor())
        ->postJson("/api/admin/message/announcements/{$announcement->id}/publish")
        ->assertOk();

    expect(Notification::query()->about($announcement)->where('notifiable_id', $recipient->id)->count())->toBe(1);
});

it('returns 404 for unknown and malformed ids', function (string $id): void {
    $this->actingAs(superAdminActor())->postJson("/api/admin/message/announcements/{$id}/publish")->assertNotFound();
})->with(['missing' => '999999', 'not a number' => 'abc']);

it('returns 422 when the announcement was published by someone else in the meantime', function (): void {
    $announcement = Announcement::factory()->create();
    $stale = Announcement::query()->findOrFail($announcement->id);
    $actor = User::factory()->create();
    Bus::fake();

    resolve(PublishAnnouncement::class)->handle($announcement, $actor);

    expect(fn () => resolve(PublishAnnouncement::class)->handle($stale, $actor))
        ->toThrow(BusinessRuleViolation::class, 'Only draft announcements can be published.');

    Bus::assertDispatchedTimes(SendAnnouncement::class, 1);
});
