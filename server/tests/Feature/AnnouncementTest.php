<?php

use App\Jobs\{SendAnnouncementNotificationChunk, SendAnnouncementNotifications};
use App\Models\{Announcement, User};
use App\Notifications\AnnouncementPublished;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\{Bus, Notification, Queue};

beforeEach(function () {
    $this->admin = actingAsUser(superAdmin());
});

test('announcements support crud', function () {
    $id = $this->postJson('/api/announcements', ['title' => '公告', 'content' => '内容', 'type' => 'notice'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.created_by', $this->admin->id)
        ->json('data.id');

    $this->getJson('/api/announcements?status=draft')->assertOk()->assertJsonCount(1, 'data');
    $this->putJson("/api/announcements/{$id}", ['title' => '新标题', 'content' => '内容', 'type' => 'announcement'])
        ->assertOk()->assertJsonPath('data.title', '新标题');
    $this->deleteJson("/api/announcements/{$id}")->assertNoContent();
});

test('publishing queues notifications for active users', function () {
    Queue::fake();
    $announcement = Announcement::factory()->create();

    $this->postJson("/api/announcements/{$announcement->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($announcement->fresh()->published_at)->not->toBeNull();
    Queue::assertPushed(SendAnnouncementNotifications::class);
});

test('published announcements send database notifications to active users only', function () {
    Notification::fake();
    $active = User::factory()->create();
    $disabled = User::factory()->disabled()->create();
    $announcement = Announcement::factory()->create();

    $this->postJson("/api/announcements/{$announcement->id}/publish")->assertOk();

    Notification::assertSentTo([$this->admin, $active], AnnouncementPublished::class,
        fn (AnnouncementPublished $notification, array $channels) => $channels === ['database']
            && $notification->toArray($active)['announcement_id'] === $announcement->id);
    Notification::assertNotSentTo($disabled, AnnouncementPublished::class);
});

test('published announcements cannot be edited or published again', function () {
    $announcement = Announcement::factory()->published()->create();

    $this->putJson("/api/announcements/{$announcement->id}", ['title' => 'x', 'content' => 'x', 'type' => 'notice'])
        ->assertForbidden();
    $this->postJson("/api/announcements/{$announcement->id}/publish")->assertForbidden();
});

test('notifications are sent as a batch with one job per chunk of active users', function () {
    Bus::fake();
    User::factory(2)->create();
    User::factory()->disabled()->create();
    $announcement = Announcement::factory()->create();

    (new SendAnnouncementNotifications($announcement))->handle();

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->name === "announcement:{$announcement->id}"
        && $batch->allowsFailures()
        && $batch->jobs->count() === 1
        && $batch->jobs->first() instanceof SendAnnouncementNotificationChunk
        && count($batch->jobs->first()->userIds) === 3);
});

test('a chunk skips users that were disabled after it was queued', function () {
    Notification::fake();
    [$active, $disabled] = User::factory(2)->create();
    $announcement = Announcement::factory()->create();
    $disabled->update(['status' => 'disabled']);

    (new SendAnnouncementNotificationChunk($announcement, [$active->id, $disabled->id]))->handle();

    Notification::assertSentTo($active, AnnouncementPublished::class);
    Notification::assertNotSentTo($disabled, AnnouncementPublished::class);
});
