<?php

use App\Core\Message\Models\Announcement;
use App\Core\System\Models\{Attachment, Role, User};
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 公告管理：/message/announcements 的列表、详情、新增、修改、删除与目标角色下拉（设计文档 §6.9、§8.6）
|--------------------------------------------------------------------------
|
| 发布、撤回见 PublishAnnouncementControllerTest、RevokeAnnouncementControllerTest；分批发送见 tests/Core/Message/Jobs。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    Storage::fake('local');
});

/**
 * 新增公告的请求体。
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function announcementPayload(array $overrides = []): array
{
    return [...['title' => 'Maintenance', 'content' => '<p>Tonight.</p>', 'target' => 'all'], ...$overrides];
}

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/message/announcements')->assertUnauthorized();
    });

    it('returns 403 without the message.announcement code', function (): void {
        $this->actingAs(userWithPermissions('system.user'))
            ->getJson('/api/admin/message/announcements')
            ->assertForbidden();
    });

    it('paginates the announcements with their fields, without content and attachments', function (): void {
        $role = Role::factory()->create(['name' => 'ops', 'title' => 'Ops']);
        $announcement = Announcement::factory()->published()->forRoles($role)->create(['title' => 'Maintenance', 'level' => 'warning']);
        Announcement::factory()->create();
        $publisher = $announcement->publisher;

        $this->actingAs(userWithDataScope('all', 'message.announcement'))
            ->getJson('/api/admin/message/announcements?sort=id&pageSize=1')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['total' => 2, 'items' => [[
                'id' => $announcement->id,
                'title' => 'Maintenance',
                'level' => 'warning',
                'status' => 'published',
                'target' => 'roles',
                'roles' => [['name' => 'ops', 'title' => 'Ops']],
                'published_at' => $announcement->published_at?->toIso8601String(),
                'publisher' => ['id' => $publisher?->id, 'username' => $publisher?->username, 'name' => $publisher?->name],
                'revoked_at' => null,
                'created_by' => $announcement->created_by,
                'created_at' => $announcement->created_at?->toIso8601String(),
                'updated_at' => $announcement->updated_at?->toIso8601String(),
            ]]]]);
    });

    it('only lists the announcements within the data scope', function (): void {
        $actor = userWithPermissions('message.announcement');
        $own = Announcement::factory()->create(['created_by' => $actor->id]);
        Announcement::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/message/announcements')
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$own->id]);
    });

    it('filters by title, status, level, target and time ranges', function (string $query, int $count): void {
        Announcement::factory()->create(['title' => 'Holiday notice', 'level' => 'info', 'created_at' => '2026-11-01 10:00:00']);
        Announcement::factory()->published()->forRoles(Role::factory()->create())->create(['title' => 'Maintenance', 'level' => 'error', 'published_at' => '2026-11-10 10:00:00']);
        Announcement::factory()->revoked()->create(['title' => 'Old maintenance', 'published_at' => '2026-10-10 10:00:00']);

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/message/announcements?{$query}")
            ->assertOk()
            ->assertJsonPath('data.total', $count);
    })->with([
        'title contains' => ['filter[title]=maintenance', 2],
        'status' => ['filter[status]=revoked', 1],
        'level' => ['filter[level]=error', 1],
        'target' => ['filter[target]=roles', 1],
        'published at' => ['filter[published_at]=2026-11-01T00:00:00%2B08:00,', 1],
        'created at' => ['filter[created_at]=,2026-11-02T00:00:00%2B08:00', 1],
    ]);

    it('returns 422 for filters and sorts outside the allowlist', function (string $query, string $field): void {
        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/message/announcements?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'filter' => ['filter[content]=x', 'filter'],
        'sort' => ['sort=title', 'sort'],
        'status value' => ['filter[status]=deleted', 'filter.status'],
    ]);
});

describe('show', function (): void {
    it('returns 403 without the code or outside the data scope', function (): void {
        $announcement = Announcement::factory()->create();

        $this->actingAs(userWithPermissions('system.user'))->getJson("/api/admin/message/announcements/{$announcement->id}")->assertForbidden();
        $this->actingAs(userWithPermissions('message.announcement'))->getJson("/api/admin/message/announcements/{$announcement->id}")->assertForbidden();
    });

    it('returns the announcement with its content and signed attachment urls', function (): void {
        $announcement = Announcement::factory()->create(['content' => '<p>Body</p>']);
        $file = Attachment::factory()->boundTo($announcement, Announcement::ATTACHMENT_COLLECTION)->create();

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/message/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('data.content', '<p>Body</p>')
            ->assertJsonPath('data.attachments.0.id', $file->id)
            ->assertJsonPath('data.attachments.0.download_url', fn (string $url): bool => str_contains($url, "/attachments/{$file->id}/download?expires="));
    });

    it('returns 404 for unknown and malformed ids', function (string $id): void {
        $this->actingAs(superAdminActor())->getJson("/api/admin/message/announcements/{$id}")->assertNotFound();
    })->with(['missing' => '999999', 'not a number' => 'abc']);
});

describe('store', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/message/announcements', announcementPayload())->assertUnauthorized();
    });

    it('returns 403 without the message.announcement.create code', function (): void {
        $this->actingAs(userWithPermissions('message.announcement', 'message.announcement.update'))
            ->postJson('/api/admin/message/announcements', announcementPayload())
            ->assertForbidden();

        expect(Announcement::query()->count())->toBe(0);
    });

    it('creates a draft for the target roles with sanitized content and attachments', function (): void {
        $actor = userWithPermissions('message.announcement.create');
        Role::factory()->create(['name' => 'ops', 'title' => 'Ops']);
        $upload = Attachment::factory()->create(['created_by' => $actor->id]);

        $this->actingAs($actor)
            ->postJson('/api/admin/message/announcements', announcementPayload([
                'content' => '<p onclick="steal()">Tonight<script>alert(1)</script></p>',
                'level' => 'warning',
                'target' => 'roles',
                'roles' => ['ops'],
                'attachment_ids' => [$upload->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.level', 'warning')
            ->assertJsonPath('data.target', 'roles')
            ->assertJsonPath('data.roles', [['name' => 'ops', 'title' => 'Ops']])
            ->assertJsonPath('data.content', '<p>Tonight</p>')
            ->assertJsonPath('data.attachments.*.id', [$upload->id])
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.publisher', null)
            ->assertJsonPath('data.created_by', $actor->id);

        $announcement = Announcement::query()->sole();

        expect($announcement->content)->toBe('<p>Tonight</p>')
            ->and($upload->refresh()->isBoundTo($announcement, Announcement::ATTACHMENT_COLLECTION))->toBeTrue();
    });

    it('defaults to the info level for all users', function (): void {
        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/message/announcements', announcementPayload())
            ->assertCreated()
            ->assertJsonPath('data.level', 'info')
            ->assertJsonPath('data.target', 'all')
            ->assertJsonPath('data.roles', [])
            ->assertJsonPath('data.attachments', []);
    });

    it('validates the fields', function (Closure $payload, string $field): void {
        Role::factory()->create(['name' => 'ops']);

        $this->actingAs(superAdminActor())
            ->postJson('/api/admin/message/announcements', announcementPayload($payload()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        expect(Announcement::query()->count())->toBe(0);
    })->with([
        'missing title' => [fn (): array => ['title' => null], 'title'],
        'title too long' => [fn (): array => ['title' => str_repeat('a', 256)], 'title'],
        'missing content' => [fn (): array => ['content' => null], 'content'],
        'content too long' => [fn (): array => ['content' => str_repeat('a', Announcement::CONTENT_MAX_LENGTH + 1)], 'content'],
        'unknown level' => [fn (): array => ['level' => 'urgent'], 'level'],
        'missing target' => [fn (): array => ['target' => null], 'target'],
        'unknown target' => [fn (): array => ['target' => 'users'], 'target'],
        'roles missing for roles' => [fn (): array => ['target' => 'roles'], 'roles'],
        'empty roles for roles' => [fn (): array => ['target' => 'roles', 'roles' => []], 'roles'],
        'roles for all users' => [fn (): array => ['target' => 'all', 'roles' => ['ops']], 'roles'],
        'unknown role' => [fn (): array => ['target' => 'roles', 'roles' => ['missing']], 'roles.0'],
        'attachment of another user' => [fn (): array => ['attachment_ids' => [Attachment::factory()->create()->id]], 'attachment_ids.0'],
        'attachment that is bound elsewhere' => [fn (): array => ['attachment_ids' => [Attachment::factory()->boundTo(User::factory()->create())->create()->id]], 'attachment_ids.0'],
        'status is not accepted' => [fn (): array => ['status' => 'published'], 'status'],
    ]);
});

describe('update', function (): void {
    it('returns 403 without the message.announcement.update code', function (): void {
        $announcement = Announcement::factory()->create();

        $this->actingAs(userWithDataScope('all', 'message.announcement', 'message.announcement.create'))
            ->putJson("/api/admin/message/announcements/{$announcement->id}", announcementPayload())
            ->assertForbidden();
    });

    it('returns 403 outside the data scope', function (): void {
        $announcement = Announcement::factory()->create();

        $this->actingAs(userWithPermissions('message.announcement.update'))
            ->putJson("/api/admin/message/announcements/{$announcement->id}", announcementPayload())
            ->assertForbidden();
    });

    it('returns 403 for published and revoked announcements', function (string $state): void {
        $announcement = Announcement::factory()->{$state}()->create(['title' => 'Kept']);

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/message/announcements/{$announcement->id}", announcementPayload())
            ->assertForbidden()
            ->assertJsonPath('message', 'Only draft announcements can be edited.');

        expect($announcement->refresh()->title)->toBe('Kept');
    })->with(['published', 'revoked']);

    it('updates a draft, replacing the target roles and keeping the attachments that were not submitted', function (): void {
        $actor = userWithPermissions('message.announcement.update');
        $role = Role::factory()->create(['name' => 'ops']);
        $announcement = Announcement::factory()->forRoles($role)->create(['created_by' => $actor->id, 'level' => 'error']);
        $file = Attachment::factory()->boundTo($announcement, Announcement::ATTACHMENT_COLLECTION)->create();

        $this->actingAs($actor)
            ->putJson("/api/admin/message/announcements/{$announcement->id}", announcementPayload(['title' => 'New title']))
            ->assertOk()
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.level', 'error')
            ->assertJsonPath('data.target', 'all')
            ->assertJsonPath('data.roles', [])
            ->assertJsonPath('data.attachments.*.id', [$file->id]);

        expect($announcement->roles()->count())->toBe(0);
    });

    it('removes the attachments when an empty list is submitted', function (): void {
        $announcement = Announcement::factory()->create();
        $file = Attachment::factory()->boundTo($announcement, Announcement::ATTACHMENT_COLLECTION)->create();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/message/announcements/{$announcement->id}", announcementPayload(['attachment_ids' => []]))
            ->assertOk()
            ->assertJsonPath('data.attachments', []);

        expect(Attachment::query()->find($file->id))->toBeNull();
    });

    it('keeps its own attachments when they are submitted again', function (): void {
        $announcement = Announcement::factory()->create();
        $file = Attachment::factory()->boundTo($announcement, Announcement::ATTACHMENT_COLLECTION)->create();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/message/announcements/{$announcement->id}", announcementPayload(['attachment_ids' => [$file->id]]))
            ->assertOk()
            ->assertJsonPath('data.attachments.*.id', [$file->id]);
    });

    it('validates the fields', function (): void {
        $announcement = Announcement::factory()->create();

        $this->actingAs(superAdminActor())
            ->putJson("/api/admin/message/announcements/{$announcement->id}", announcementPayload(['target' => 'roles', 'roles' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['roles']);
    });
});

describe('destroy', function (): void {
    it('returns 403 without the message.announcement.delete code', function (): void {
        $announcement = Announcement::factory()->create();

        $this->actingAs(userWithDataScope('all', 'message.announcement'))
            ->deleteJson("/api/admin/message/announcements/{$announcement->id}")
            ->assertForbidden();
    });

    it('returns 403 for published announcements', function (): void {
        $announcement = Announcement::factory()->published()->create();

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/message/announcements/{$announcement->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Published announcements must be revoked before they can be deleted.');

        expect(Announcement::query()->find($announcement->id))->not->toBeNull();
    });

    it('deletes drafts and revoked announcements with their attachments and target roles', function (string $state): void {
        $role = Role::factory()->create();
        $factory = Announcement::factory()->forRoles($role);
        $announcement = ($state === 'revoked' ? $factory->revoked() : $factory)->create();
        $file = Attachment::factory()->boundTo($announcement, Announcement::ATTACHMENT_COLLECTION)->withFile()->create();

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/message/announcements/{$announcement->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => null]);

        expect(Announcement::query()->find($announcement->id))->toBeNull()
            ->and(Attachment::query()->find($file->id))->toBeNull()
            ->and(Role::query()->find($role->id))->not->toBeNull();

        $this->assertDatabaseMissing('announcement_role', ['announcement_id' => $announcement->id]);
        Storage::disk('local')->assertMissing($file->path);
    })->with(['draft' => 'draft', 'revoked' => 'revoked']);
});

describe('role options', function (): void {
    it('returns 403 without the create or update code', function (): void {
        $this->actingAs(userWithPermissions('message.announcement', 'system.user.create'))
            ->getJson('/api/admin/message/announcements/role-options')
            ->assertForbidden();
    });

    it('lists the enabled roles with the create or update code', function (string $code): void {
        Role::factory()->create(['name' => 'ops', 'title' => 'Ops', 'sort' => 2]);
        Role::factory()->create(['name' => 'hr', 'title' => 'HR', 'sort' => 1]);
        Role::factory()->disabled()->create(['name' => 'old']);

        $options = $this->actingAs(userWithPermissions($code))
            ->getJson('/api/admin/message/announcements/role-options')
            ->assertOk()
            ->json('data');

        $values = array_column($options, 'value');

        expect($options)->toContain(['value' => 'hr', 'label' => 'HR'], ['value' => 'ops', 'label' => 'Ops'])
            ->and(array_search('hr', $values, true))->toBeLessThan(array_search('ops', $values, true))
            ->and($values)->not->toContain('old');
    })->with(['message.announcement.create', 'message.announcement.update']);
});
