<?php

use App\Core\System\Models\{Attachment, Export, User};
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 我的导出：/exports 的列表、详情、删除，以及文件的下载权限（设计文档 §6.10）
|--------------------------------------------------------------------------
|
| 文件是绑定在导出记录 `file` 用途上的附件，下载走附件的签名路由（§6.8）。访问权的完整矩阵见 tests/Core/System/Policies/ExportPolicyTest.php。
|
*/

beforeEach(function (): void {
    syncBuiltinPermissions();
    Storage::fake('local');
});

/**
 * 已完成、带文件的导出。
 */
function completedExport(?User $owner = null): Export
{
    $export = Export::factory()->for($owner ?? User::factory()->create())->completed(3)->create();
    Attachment::factory()->ofType('text/csv', 'csv')->boundTo($export, Export::FILE_COLLECTION)->withFile("id\n1\n")->create(['created_by' => $export->user_id]);

    return $export;
}

describe('index', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/exports')->assertUnauthorized();
    });

    it('lists only the own exports with the signed download URL of the file', function (): void {
        $actor = User::factory()->create();
        $mine = completedExport($actor);
        $queued = Export::factory()->for($actor)->create();
        completedExport();

        $response = $this->actingAs($actor)
            ->getJson('/api/admin/exports')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.items.0.id', $queued->id)
            ->assertJsonPath('data.items.0.status', 'queued')
            ->assertJsonPath('data.items.0.file', null)
            ->assertJsonPath('data.items.0.params', [])
            ->assertJsonPath('data.items.1.id', $mine->id)
            ->assertJsonPath('data.items.1.row_count', 3)
            ->assertJsonPath('data.items.1.title', 'Users');

        expect($response->json('data.items.1.file.download_url'))->toContain('/api/admin/attachments/'.$mine->file()->sole()->id.'/download?expires=');
    });

    it('filters by name, status, format and creation time', function (): void {
        $actor = User::factory()->create();
        $csv = Export::factory()->for($actor)->failed()->create(['format' => 'csv']);
        Export::factory()->for($actor)->create();

        $this->actingAs($actor)
            ->getJson('/api/admin/exports?filter[name]=user&filter[status]=failed&filter[format]=csv&filter[created_at]='.urlencode(now()->subHour()->toIso8601String().','))
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.items.0.id', $csv->id);
    });

    it('rejects filters outside of the whitelist', function (string $query, string $error): void {
        $this->actingAs(User::factory()->create())
            ->getJson("/api/admin/exports?{$query}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors($error);
    })->with([
        'unknown filter' => ['filter[user_id]=1', 'filter'],
        'unknown sort' => ['sort=row_count', 'sort'],
        'invalid status' => ['filter[status]=done', 'filter.status'],
    ]);
});

describe('show', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/exports/'.completedExport()->id)->assertUnauthorized();
    });

    it('returns the own export with a freshly signed download URL', function (): void {
        $export = completedExport();

        $response = $this->actingAs($export->user()->sole())
            ->getJson("/api/admin/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $export->id)
            ->assertJsonPath('data.file.name', $export->file()->sole()->name);

        $this->get($response->json('data.file.download_url'))->assertOk()->assertDownload();
    });

    it('returns 403 for the export of someone else, even for super admins', function (bool $superAdmin): void {
        $this->actingAs($superAdmin ? superAdminActor() : User::factory()->create())
            ->getJson('/api/admin/exports/'.completedExport()->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'You can only access your own exports.');
    })->with([
        'a user' => [false],
        'a super admin' => [true],
    ]);

    it('returns 404 for a non-numeric id', function (): void {
        $this->actingAs(User::factory()->create())->getJson('/api/admin/exports/abc')->assertNotFound();
    });
});

describe('download', function (): void {
    it('lets only the actor reach the file through the attachment endpoints', function (): void {
        $export = completedExport();
        $file = $export->file()->sole();
        $admin = userWithDataScope('all', 'system.attachment', 'system.attachment.delete');

        $this->actingAs($export->user()->sole())->getJson("/api/admin/attachments/{$file->id}")->assertOk();
        $this->actingAs($admin)->getJson("/api/admin/attachments/{$file->id}")->assertForbidden();
        $this->actingAs(superAdminActor())->getJson("/api/admin/attachments/{$file->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/admin/system/attachments/{$file->id}")->assertForbidden();

        expect(collect($this->actingAs($admin)->getJson('/api/admin/system/attachments')->assertOk()->json('data.items'))->pluck('id'))
            ->not->toContain($file->id);
    });
});

describe('destroy', function (): void {
    it('returns 401 for guests', function (): void {
        $this->deleteJson('/api/admin/exports/'.completedExport()->id)->assertUnauthorized();
    });

    it('deletes the own export together with its file', function (): void {
        $export = completedExport();
        $file = $export->file()->sole();

        $this->actingAs($export->user()->sole())
            ->deleteJson("/api/admin/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        expect(Export::query()->count())->toBe(0)
            ->and(Attachment::query()->count())->toBe(0);
        Storage::disk('local')->assertMissing($file->path);
    });

    it('returns 403 for the export of someone else', function (): void {
        $export = completedExport();

        $this->actingAs(superAdminActor())->deleteJson("/api/admin/exports/{$export->id}")->assertForbidden();

        expect(Export::query()->count())->toBe(1);
    });
});
