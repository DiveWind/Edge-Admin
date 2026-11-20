<?php

use App\Core\Monitor\Models\OperationLog;
use App\Core\System\Actions\UpdateSettings;
use App\Core\System\Models\{Attachment, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 个人的附件：上传与详情（/attachments，MyAttachmentController），设计文档 §6.8；附件管理见 AttachmentControllerTest.php
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

describe('store', function (): void {
    it('returns 401 for guests', function (): void {
        $this->postJson('/api/admin/attachments', ['file' => uploadedFile('photo.png', pngBytes())])->assertUnauthorized();

        expect(Attachment::query()->count())->toBe(0);
    });

    it('stores the file under a random name in a dated directory of the private disk for any logged-in user', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/api/admin/attachments', ['file' => uploadedFile('我的 照片.png', pngBytes(), 'application/octet-stream')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', '我的 照片.png')
            ->assertJsonPath('data.mime_type', 'image/png')
            ->assertJsonPath('data.extension', 'png')
            ->assertJsonPath('data.size', strlen(pngBytes()))
            ->assertJsonPath('data.sha256', hash('sha256', pngBytes()))
            ->assertJsonPath('data.is_bound', false)
            ->assertJsonPath('data.created_by', $user->id)
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.disk');

        $attachment = Attachment::query()->sole();

        expect($attachment)
            ->disk->toBe('local')
            ->created_by->toBe($user->id)
            ->attachable_type->toBeNull()
            ->and($attachment->path)->toMatch('#^attachments/'.now()->format('Y/m/d').'/[A-Za-z0-9]{40}\.png$#')
            ->and($response->json('data.download_url'))->toContain("/api/admin/attachments/{$attachment->id}/download?expires=")
            ->and($response->json('data.preview_url'))->toContain("/api/admin/attachments/{$attachment->id}/preview?expires=");

        Storage::disk('local')->assertExists($attachment->path);
    });

    it('rejects files whose content, extension or size is not allowed', function (string $name, string $contents, ?string $clientMimeType): void {
        $this->actingAs(User::factory()->create())
            ->post('/api/admin/attachments', ['file' => uploadedFile($name, $contents, $clientMimeType)], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        expect(Attachment::query()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'PHP code renamed to .png' => ['avatar.png', '<?php echo "pwned";', null],
        'HTML with a forged image Content-Type' => ['photo.png', '<html><body><script>alert(1)</script></body></html>', 'image/png'],
        'an image with a disallowed extension' => ['photo.exe', pngBytes(), 'image/png'],
        'SVG (not in the default whitelist)' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'image/svg+xml'],
    ]);

    it('reads the whitelist and the size limit from the system settings', function (): void {
        $user = User::factory()->create();
        resolve(UpdateSettings::class)->handle(['attachment.allowed_extensions' => 'pdf', 'attachment.max_size' => 1]);

        $this->actingAs($user)
            ->post('/api/admin/attachments', ['file' => uploadedFile('photo.png', pngBytes())], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        resolve(UpdateSettings::class)->handle(['attachment.allowed_extensions' => 'png', 'attachment.max_size' => 1]);

        $this->actingAs($user)
            ->post('/api/admin/attachments', ['file' => uploadedFile('big.png', pngBytes().str_repeat("\0", 2048))], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file', ['The file field must not be greater than 1 kilobytes.']);

        $this->actingAs($user)
            ->post('/api/admin/attachments', ['file' => uploadedFile('photo.png', pngBytes())], ['Accept' => 'application/json'])
            ->assertCreated();
    });

    it('requires a file and rejects unknown fields', function (): void {
        $this->actingAs(User::factory()->create())
            ->post('/api/admin/attachments', ['collection' => 'avatar'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file', 'collection']);
    });

    it('does not store the same file twice into one attachment', function (): void {
        $user = User::factory()->create();

        foreach ([1, 2] as $ignored) {
            $this->actingAs($user)->post('/api/admin/attachments', ['file' => uploadedFile('photo.png', pngBytes())], ['Accept' => 'application/json'])->assertCreated();
        }

        expect(Attachment::query()->pluck('path')->unique())->toHaveCount(2);
    });

    it('throttles uploads per user with the upload limiter', function (): void {
        config(['core.apps.admin.upload_rate_limit' => 1]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/api/admin/attachments', ['file' => uploadedFile('a.png', pngBytes())], ['Accept' => 'application/json'])->assertCreated();
        $this->actingAs($user)->post('/api/admin/attachments', ['file' => uploadedFile('b.png', pngBytes())], ['Accept' => 'application/json'])->assertTooManyRequests();
        $this->actingAs(User::factory()->create())->post('/api/admin/attachments', ['file' => uploadedFile('c.png', pngBytes())], ['Accept' => 'application/json'])->assertCreated();
    });

    it('keeps the uploaded file out of the operation log', function (): void {
        $this->actingAs(User::factory()->create())
            ->post('/api/admin/attachments', ['file' => uploadedFile('photo.png', pngBytes())], ['Accept' => 'application/json'])
            ->assertCreated();

        expect(OperationLog::query()->sole())
            ->description->toBe('admin.attachments.store')
            ->and(OperationLog::query()->sole()->getProperty('payload'))->toBe([]);
    });
});

describe('show', function (): void {
    it('returns 401 for guests', function (): void {
        $this->getJson('/api/admin/attachments/'.Attachment::factory()->create()->id)->assertUnauthorized();
    });

    it('returns the own unbound attachment with fresh URLs', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:10:00'));
        $attachment = Attachment::factory()->create();

        $this->actingAs($attachment->creator()->sole())
            ->getJson("/api/admin/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $attachment->id)
            ->assertJsonPath('data.url_expires_at', CarbonImmutable::parse('2026-11-20 09:00:00')->toIso8601String());
    });

    it('returns 403 for the unbound attachment of someone else', function (): void {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/admin/attachments/'.Attachment::factory()->create()->id)
            ->assertForbidden();
    });

    it('lets the people who can view the owning record view a bound attachment', function (): void {
        $owner = User::factory()->create();
        $attachment = Attachment::factory()->for($owner, 'creator')->boundTo($owner, User::AVATAR_COLLECTION)->create();

        $this->actingAs(userWithDataScope('all', 'system.user'))->getJson("/api/admin/attachments/{$attachment->id}")->assertOk();
        $this->actingAs($owner)->getJson("/api/admin/attachments/{$attachment->id}")->assertForbidden();
    });

    it('returns 404 for a missing attachment and a non-numeric id', function (): void {
        $this->actingAs(superAdminActor())->getJson('/api/admin/attachments/999')->assertNotFound();
        $this->actingAs(superAdminActor())->getJson('/api/admin/attachments/abc')->assertNotFound();
    });
});
