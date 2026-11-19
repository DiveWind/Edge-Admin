<?php

use App\Core\Message\Actions\RevokeAnnouncement;
use App\Core\Message\Models\{Announcement, Notification};
use App\Core\System\Models\User;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\Core\Message\Fixtures\DemoNotification;

/*
|--------------------------------------------------------------------------
| 撤回公告：POST /message/announcements/{announcement}/revoke（设计文档 §6.9）
|--------------------------------------------------------------------------
|
| 撤回删除已发出的通知；撤回是终态：不能再发布、修改，只能删除。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
});

it('returns 401 for guests', function (): void {
    $announcement = Announcement::factory()->published()->create();

    $this->postJson("/api/admin/message/announcements/{$announcement->id}/revoke")->assertUnauthorized();
});

it('returns 403 without the message.announcement.revoke code', function (): void {
    $announcement = Announcement::factory()->published()->create();

    $this->actingAs(userWithDataScope('all', 'message.announcement', 'message.announcement.publish'))
        ->postJson("/api/admin/message/announcements/{$announcement->id}/revoke")
        ->assertForbidden();

    expect($announcement->refresh()->isPublished())->toBeTrue();
});

it('returns 403 for announcements that are not published', function (string $state): void {
    $factory = Announcement::factory();
    $announcement = ($state === 'revoked' ? $factory->revoked() : $factory)->create();

    $this->actingAs(superAdminActor())
        ->postJson("/api/admin/message/announcements/{$announcement->id}/revoke")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only published announcements can be revoked.');
})->with(['draft', 'revoked']);

it('revokes the announcement and deletes the notifications it sent', function (): void {
    $actor = superAdminActor();
    $recipients = User::factory()->count(2)->create();
    $announcement = Announcement::factory()->create();
    $other = Announcement::factory()->create();

    $this->actingAs($actor)->postJson("/api/admin/message/announcements/{$announcement->id}/publish")->assertOk();
    $this->actingAs($actor)->postJson("/api/admin/message/announcements/{$other->id}/publish")->assertOk();
    $recipients[0]->notify(new DemoNotification);

    expect(Notification::query()->about($announcement)->count())->toBeGreaterThanOrEqual(2);

    $this->actingAs($actor)
        ->postJson("/api/admin/message/announcements/{$announcement->id}/revoke")
        ->assertOk()
        ->assertJsonPath('data.status', 'revoked')
        ->assertJsonPath('data.revoked_at', now()->toIso8601String());

    expect(Notification::query()->about($announcement)->count())->toBe(0)
        ->and(Notification::query()->about($other)->count())->toBeGreaterThanOrEqual(2)
        ->and(Notification::query()->where('type', 'demo.example')->count())->toBe(1);

    $this->actingAs($recipients[1])->getJson("/api/admin/announcements/{$announcement->id}")->assertForbidden();
});

it('is final: a revoked announcement cannot be published or edited again, only deleted', function (): void {
    $actor = superAdminActor();
    $announcement = Announcement::factory()->revoked()->create();

    $this->actingAs($actor)->postJson("/api/admin/message/announcements/{$announcement->id}/publish")->assertForbidden();
    $this->actingAs($actor)->putJson("/api/admin/message/announcements/{$announcement->id}", ['title' => 'x', 'content' => 'y', 'target' => 'all'])->assertForbidden();
    $this->actingAs($actor)->deleteJson("/api/admin/message/announcements/{$announcement->id}")->assertOk();
});

it('returns 422 when the announcement was revoked by someone else in the meantime', function (): void {
    $announcement = Announcement::factory()->published()->create();
    $stale = Announcement::query()->findOrFail($announcement->id);

    resolve(RevokeAnnouncement::class)->handle($announcement);

    expect(fn () => resolve(RevokeAnnouncement::class)->handle($stale))
        ->toThrow(UnprocessableEntityHttpException::class, 'Only published announcements can be revoked.');
});
