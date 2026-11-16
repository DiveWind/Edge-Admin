<?php

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;

beforeEach(function () {
    $this->user = actingAsUser(User::factory()->create());

    foreach (Announcement::factory(3)->published()->create() as $announcement) {
        $this->user->notify(new AnnouncementPublished($announcement));
    }
});

test('users can list their notifications', function () {
    User::factory()->create()->notify(new AnnouncementPublished(Announcement::factory()->create()));

    $this->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'type', 'title', 'content', 'read_at', 'created_at']], 'meta']);

    $this->getJson('/api/notifications/unread-count')->assertOk()->assertExactJson(['data' => ['count' => 3]]);
});

test('notifications can be marked as read and filtered', function () {
    $id = $this->user->notifications()->first()->id;

    $this->postJson("/api/notifications/{$id}/read")->assertNoContent();

    $this->getJson('/api/notifications?read=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/notifications?read=0')->assertOk()->assertJsonCount(2, 'data');

    $this->postJson('/api/notifications/read-all')->assertNoContent();
    $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.count', 0);
});

test('notifications can be deleted', function () {
    $id = $this->user->notifications()->first()->id;

    $this->deleteJson("/api/notifications/{$id}")->assertNoContent();

    expect($this->user->notifications()->count())->toBe(2);
});

test('users cannot touch notifications of others', function () {
    $other = User::factory()->create();
    $other->notify(new AnnouncementPublished(Announcement::factory()->create()));

    $this->postJson("/api/notifications/{$other->notifications()->first()->id}/read")->assertNotFound();
});
