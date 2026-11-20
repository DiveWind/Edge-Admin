<?php

use App\Core\System\Models\{Attachment, User};
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 附件管理：列表与删除（/system/attachments，AttachmentController），设计文档 §6.8；上传与详情见 MyAttachmentControllerTest.php
|--------------------------------------------------------------------------
|
| 存储盘用 Storage::fake()。访问权（AttachmentPolicy::view）的完整矩阵见 tests/Core/System/Policies/AttachmentPolicyTest.php，
| 下载与预览的签名路由见 AttachmentDownloadControllerTest.php。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    Storage::fake('local');
});

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/system/attachments')->assertUnauthorized();
    });

    it('returns 403 without the system.attachment code', function (): void {
        $this->actingAs(userWithDataScope('all', 'system.user'))->getJson('/api/admin/system/attachments')->assertForbidden();
    });

    it('lists the attachments within the data scope with their uploader', function (): void {
        $actor = userWithPermissions('system.attachment');
        $own = Attachment::factory()->for($actor, 'creator')->create();
        Attachment::factory()->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/system/attachments')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $own->id)
            ->assertJsonPath('data.items.0.creator', ['id' => $actor->id, 'username' => $actor->username, 'name' => $actor->name]);

        $this->actingAs(userWithDataScope('all', 'system.attachment'))
            ->getJson('/api/admin/system/attachments')
            ->assertJsonPath('data.total', 2);
    });

    it('filters and sorts the list', function (string $filter, string $value, string $expected): void {
        $alice = User::factory()->create();
        $attachments = [
            'report' => Attachment::factory()->for($alice, 'creator')->ofType('application/pdf', 'pdf')->create(['name' => 'report.pdf', 'created_at' => now()->subDays(3)]),
            'avatar' => Attachment::factory()->boundTo($alice, User::AVATAR_COLLECTION)->create(['name' => 'avatar.png']),
        ];

        $value = $value === ':alice' ? (string) $alice->id : $value;

        $this->actingAs(userWithDataScope('all', 'system.attachment'))
            ->getJson('/api/admin/system/attachments?'.http_build_query(['filter' => [$filter => $value]]))
            ->assertOk()
            ->assertJsonPath('data.items.*.id', [$attachments[$expected]->id]);
    })->with([
        'name' => ['name', 'repo', 'report'],
        'mime type' => ['mime_type', 'image/', 'avatar'],
        'uploader' => ['created_by', ':alice', 'report'],
        'collection' => ['collection', 'avatar', 'avatar'],
        'bound' => ['is_bound', 'true', 'avatar'],
        'unbound' => ['is_bound', '0', 'report'],
        'created at' => ['created_at', now()->subDays(4)->toIso8601String().','.now()->subDays(2)->toIso8601String(), 'report'],
    ]);

    it('rejects unknown filters and invalid filter values', function (array $query, string $field): void {
        $this->actingAs(userWithDataScope('all', 'system.attachment'))
            ->getJson('/api/admin/system/attachments?'.http_build_query($query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'unknown filter' => [['filter' => ['path' => 'attachments']], 'filter'],
        'is_bound is not a boolean' => [['filter' => ['is_bound' => 'maybe']], 'filter.is_bound'],
        'unknown sort' => [['sort' => 'path'], 'sort'],
    ]);
});

describe('destroy', function (): void {
    it('returns 401 for guests', function (): void {
        $this->deleteJson('/api/admin/system/attachments/'.Attachment::factory()->create()->id)->assertUnauthorized();
    });

    it('returns 403 without the delete code, even for the uploader', function (): void {
        $attachment = Attachment::factory()->create();

        $this->actingAs($attachment->creator()->sole())->deleteJson("/api/admin/system/attachments/{$attachment->id}")->assertForbidden();

        expect($attachment->fresh())->not->toBeNull();
    });

    it('deletes an unbound attachment together with its file', function (): void {
        $attachment = Attachment::factory()->withFile()->create();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs(userWithDataScope('all', 'system.attachment.delete'))
            ->deleteJson("/api/admin/system/attachments/{$attachment->id}")
            ->assertOk()
            ->assertExactJson(['code' => 0, 'data' => null, 'message' => 'ok']);

        expect($attachment->fresh())->toBeNull();
        Storage::disk('local')->assertMissing($attachment->path);
    });

    it('refuses to delete a bound attachment, even for super admins', function (): void {
        $owner = User::factory()->create();
        $attachment = Attachment::factory()->boundTo($owner, User::AVATAR_COLLECTION)->withFile()->create();

        $this->actingAs(superAdminActor())
            ->deleteJson("/api/admin/system/attachments/{$attachment->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Attachments in use cannot be deleted.');

        expect($attachment->fresh())->not->toBeNull();
        Storage::disk('local')->assertExists($attachment->path);
    });

    it('returns 403 outside the data scope', function (): void {
        $attachment = Attachment::factory()->create();

        $this->actingAs(userWithPermissions('system.attachment.delete'))
            ->deleteJson("/api/admin/system/attachments/{$attachment->id}")
            ->assertForbidden();
    });
});
