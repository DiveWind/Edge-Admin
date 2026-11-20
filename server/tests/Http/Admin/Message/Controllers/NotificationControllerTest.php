<?php

use App\Core\Message\Models\Notification;
use App\Core\System\Models\User;
use Tests\Core\Message\Fixtures\DemoNotification;

/*
|--------------------------------------------------------------------------
| 我的通知：/notifications 的列表、未读数、标记已读、全部已读、删除（设计文档 §6.9）
|--------------------------------------------------------------------------
|
| 只需登录（列表、未读数、全部已读只作用于本人的通知）；单条的已读、删除只能操作本人的通知（403，超管也一样）。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->user = User::factory()->create();
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/notifications')->assertUnauthorized();
    });

    it('lists only the own notifications with the fixed fields, newest first', function (): void {
        $this->user->notify(new DemoNotification);
        $this->travel(1)->minutes();
        $this->user->notify(new DemoNotification(title: 'demo.second', link: 'https://example.com', params: []));
        superAdminActor()->notify(new DemoNotification);

        $latest = Notification::query()->ownedBy($this->user)->where('data->title', 'demo.second')->sole();

        $this->actingAs($this->user)
            ->getJson('/api/admin/notifications?pageSize=1')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['total' => 2, 'items' => [[
                'id' => $latest->id,
                'type' => 'demo.example',
                'title' => 'demo.second',
                'content' => 'demo.notification.content',
                'level' => 'warning',
                'link' => 'https://example.com',
                'params' => [],
                'is_read' => false,
                'read_at' => null,
                'created_at' => $latest->created_at?->toIso8601String(),
            ]]]]);
    });

    it('does not show the notifications of other users even to super admins', function (): void {
        $this->user->notify(new DemoNotification);

        $this->actingAs(superAdminActor())
            ->getJson('/api/admin/notifications')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    });

    it('filters by read state, type and creation time', function (string $query, int $count): void {
        Notification::factory()->ownedBy($this->user)->read()->create(['type' => 'demo.example', 'created_at' => '2026-11-01 10:00:00']);
        Notification::factory()->ownedBy($this->user)->create(['type' => 'announcement_published', 'created_at' => '2026-11-10 10:00:00']);
        Notification::factory()->ownedBy($this->user)->create(['type' => 'content.article_approved', 'created_at' => '2026-11-10 10:00:00']);

        $this->actingAs($this->user)
            ->getJson("/api/admin/notifications?{$query}")
            ->assertOk()
            ->assertJsonPath('data.total', $count);
    })->with([
        'unread' => ['filter[is_read]=false', 2],
        'read' => ['filter[is_read]=1', 1],
        'one type' => ['filter[type]=announcement_published', 1],
        'several types' => ['filter[type]=announcement_published,demo.example', 2],
        'created at' => ['filter[created_at]=2026-11-05T00:00:00%2B08:00,', 2],
    ]);

    it('returns 422 for invalid filters and sorts', function (string $query, string $field): void {
        $this->actingAs($this->user)
            ->getJson("/api/admin/notifications?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'unknown filter' => ['filter[title]=x', 'filter'],
        'unknown sort' => ['sort=type', 'sort'],
        'read state' => ['filter[is_read]=yes', 'filter.is_read'],
        'type format' => ['filter[type]=App\\Notifications\\Demo', 'filter.type'],
        'time range' => ['filter[created_at]=yesterday', 'filter.created_at'],
    ]);
});

describe('unread count', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/notifications/unread-count')->assertUnauthorized();
    });

    it('counts the own unread notifications', function (): void {
        Notification::factory()->ownedBy($this->user)->count(2)->create();
        Notification::factory()->ownedBy($this->user)->read()->create();
        Notification::factory()->create();

        $this->actingAs($this->user)
            ->getJson('/api/admin/notifications/unread-count')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['count' => 2]]);
    });
});

describe('read', function (): void {
    it('returns 401 for guests', function (): void {
        $notification = Notification::factory()->ownedBy($this->user)->create();

        $this->putJson("/api/admin/notifications/{$notification->id}/read")->assertUnauthorized();
    });

    it('marks the own notification as read and keeps the first read time', function (): void {
        $notification = Notification::factory()->ownedBy($this->user)->create();

        $this->actingAs($this->user)
            ->putJson("/api/admin/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_read', true);

        $readAt = $notification->refresh()->read_at;
        $this->travel(1)->hours();

        $this->actingAs($this->user)->putJson("/api/admin/notifications/{$notification->id}/read")->assertOk();

        expect($readAt)->not->toBeNull()
            ->and($notification->refresh()->read_at?->toIso8601String())->toBe($readAt?->toIso8601String());
    });

    it('returns 403 for the notifications of other users, even for super admins', function (): void {
        $notification = Notification::factory()->ownedBy($this->user)->create();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/notifications/{$notification->id}/read")
            ->assertForbidden()
            ->assertJsonPath('message', 'You can only access your own notifications.');

        expect($notification->refresh()->read_at)->toBeNull();
    });

    it('returns 404 for unknown and malformed ids', function (string $id): void {
        $this->actingAs($this->user)->putJson("/api/admin/notifications/{$id}/read")->assertNotFound();
    })->with(['missing' => '0b6f7f2e-4b8a-4d3c-9a51-1f9a8f0c2d11', 'not a uuid' => '123']);
});

describe('read all', function (): void {
    it('returns 401 for guests', function (): void {
        $this->putJson('/api/admin/notifications/read-all')->assertUnauthorized();
    });

    it('marks only the own unread notifications as read', function (): void {
        $read = Notification::factory()->ownedBy($this->user)->read()->create(['read_at' => '2026-11-01 10:00:00']);
        Notification::factory()->ownedBy($this->user)->count(2)->create();
        $other = Notification::factory()->create();

        $this->actingAs($this->user)
            ->putJson('/api/admin/notifications/read-all')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => null]);

        expect(Notification::query()->ownedBy($this->user)->whereNull('read_at')->count())->toBe(0)
            ->and($read->refresh()->read_at?->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00')
            ->and($other->refresh()->read_at)->toBeNull();

        $this->actingAs($this->user)->getJson('/api/admin/notifications/unread-count')->assertJsonPath('data.count', 0);
    });
});

describe('destroy', function (): void {
    it('returns 401 for guests', function (): void {
        $notification = Notification::factory()->ownedBy($this->user)->create();

        $this->deleteJson("/api/admin/notifications/{$notification->id}")->assertUnauthorized();
    });

    it('deletes the own notification', function (): void {
        $notification = Notification::factory()->ownedBy($this->user)->create();

        $this->actingAs($this->user)
            ->deleteJson("/api/admin/notifications/{$notification->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => null]);

        expect(Notification::query()->find($notification->id))->toBeNull();
    });

    it('returns 403 for the notifications of other users', function (): void {
        $notification = Notification::factory()->create();

        $this->actingAs($this->user)
            ->deleteJson("/api/admin/notifications/{$notification->id}")
            ->assertForbidden();

        expect(Notification::query()->find($notification->id))->not->toBeNull();
    });
});
