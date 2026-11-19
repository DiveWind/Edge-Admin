<?php

use App\Core\Monitor\Models\OperationLog;
use App\Core\System\Actions\UpdateSettings;
use App\Core\System\Models\{Attachment, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| 附件：上传、详情（/attachments）与附件管理（/system/attachments），设计文档 §6.8
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
