<?php

use App\Core\Message\Models\Announcement;
use App\Core\System\Models\{Attachment, Role, User};
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 我收到的公告：/announcements 的列表与详情（设计文档 §6.9）
|--------------------------------------------------------------------------
|
| 只需登录；只能看到已发布、且目标包含自己（全部用户，或当前拥有任一启用的目标角色）的公告。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    $this->role = Role::factory()->create();
    $this->user = User::factory()->create()->assignRole($this->role);
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/announcements')->assertUnauthorized();
    });

    it('lists only the published announcements sent to the user, newest first', function (): void {
        $forAll = Announcement::factory()->published()->create(['published_at' => now()->subDay()]);
        $forMyRole = Announcement::factory()->published()->forRoles($this->role)->create(['published_at' => now()]);
        Announcement::factory()->create();
        Announcement::factory()->revoked()->create();
        Announcement::factory()->published()->forRoles(Role::factory()->create())->create();
        Announcement::factory()->published()->forRoles(Role::factory()->disabled()->create())->create();

        $this->actingAs($this->user)
            ->getJson('/api/admin/announcements')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.*.id', [$forMyRole->id, $forAll->id]);
    });

    it('outputs only the fields for recipients', function (): void {
        $announcement = Announcement::factory()->published()->create(['title' => 'Holiday', 'level' => 'success']);
        $publisher = $announcement->publisher;

        $this->actingAs($this->user)
            ->getJson('/api/admin/announcements')
            ->assertOk()
            ->assertExactJson(['code' => 0, 'message' => 'ok', 'data' => ['total' => 1, 'items' => [[
                'id' => $announcement->id,
                'title' => 'Holiday',
                'level' => 'success',
                'published_at' => $announcement->published_at?->toIso8601String(),
                'publisher' => ['id' => $publisher?->id, 'username' => $publisher?->username, 'name' => $publisher?->name],
            ]]]]);
    });

    it('does not list the announcements of a disabled role of the user', function (): void {
        $disabled = Role::factory()->disabled()->create();
        $this->user->assignRole($disabled);
        Announcement::factory()->published()->forRoles($disabled)->create();

        $this->actingAs($this->user)->getJson('/api/admin/announcements')->assertOk()->assertJsonPath('data.total', 0);
    });

    it('filters by title, level and publication time', function (string $query, int $count): void {
        Announcement::factory()->published()->create(['title' => 'Holiday notice', 'level' => 'info', 'published_at' => '2026-11-01 10:00:00']);
        Announcement::factory()->published()->create(['title' => 'Maintenance', 'level' => 'error', 'published_at' => '2026-11-10 10:00:00']);

        $this->actingAs($this->user)
            ->getJson("/api/admin/announcements?{$query}")
            ->assertOk()
            ->assertJsonPath('data.total', $count);
    })->with([
        'title' => ['filter[title]=holiday', 1],
        'level' => ['filter[level]=error', 1],
        'published at' => ['filter[published_at]=2026-11-05T00:00:00%2B08:00,', 1],
    ]);

    it('returns 422 for invalid filters and sorts', function (string $query, string $field): void {
        $this->actingAs($this->user)
            ->getJson("/api/admin/announcements?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'status is not a filter' => ['filter[status]=draft', 'filter'],
        'unknown sort' => ['sort=id', 'sort'],
        'level value' => ['filter[level]=urgent', 'filter.level'],
    ]);
});

describe('show', function (): void {
    it('returns 401 for guests', function (): void {
        $announcement = Announcement::factory()->published()->create();

        $this->getJson("/api/admin/announcements/{$announcement->id}")->assertUnauthorized();
    });

    it('returns an announcement sent to the user with its content and signed attachment urls', function (): void {
        Storage::fake('local');
        $announcement = Announcement::factory()->published()->forRoles($this->role)->create(['content' => '<p>Body</p>']);
        $file = Attachment::factory()->boundTo($announcement, Announcement::ATTACHMENT_COLLECTION)->create();

        $this->actingAs($this->user)
            ->getJson("/api/admin/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('data.content', '<p>Body</p>')
            ->assertJsonPath('data.attachments.0.id', $file->id)
            ->assertJsonPath('data.attachments.0.download_url', fn (string $url): bool => str_contains($url, "/attachments/{$file->id}/download?expires="))
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.created_by');
    });

    it('never outputs unsanitized scripts', function (): void {
        $announcement = Announcement::factory()->published()->create(['content' => '<p>Hi</p><script>alert(1)</script><img src="x" onerror="alert(1)">']);

        $content = $this->actingAs($this->user)
            ->getJson("/api/admin/announcements/{$announcement->id}")
            ->assertOk()
            ->json('data.content');

        expect($content)->toContain('<p>Hi</p>')->not->toContain('<script')->not->toContain('onerror');
    });

    it('returns 403 for announcements not sent to the user', function (Announcement $announcement): void {
        $this->actingAs($this->user)
            ->getJson("/api/admin/announcements/{$announcement->id}")
            ->assertForbidden();
    })->with([
        'draft' => fn (): Announcement => Announcement::factory()->create(),
        'revoked' => fn (): Announcement => Announcement::factory()->revoked()->create(),
        'another role' => fn (): Announcement => Announcement::factory()->published()->forRoles(Role::factory()->create())->create(),
    ]);

    it('is not opened up by the management code', function (): void {
        $announcement = Announcement::factory()->create();

        $this->actingAs(superAdminActor())
            ->getJson("/api/admin/announcements/{$announcement->id}")
            ->assertForbidden();
    });

    it('returns 404 for unknown and malformed ids', function (string $id): void {
        $this->actingAs($this->user)->getJson("/api/admin/announcements/{$id}")->assertNotFound();
    })->with(['missing' => '999999', 'not a number' => 'abc']);
});
