<?php

use App\Core\System\Models\Attachment;
use App\Http\Admin\System\Resources\AttachmentResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Storage, URL};
use Symfony\Component\HttpFoundation\HeaderUtils;

/*
|--------------------------------------------------------------------------
| 附件的下载与预览：签名的临时 URL（设计文档 §6.8）
|--------------------------------------------------------------------------
|
| 签名路由不需要登录：授权在签发地址时完成（上传、附件详情、业务记录的接口）。
|
*/

beforeEach(function (): void {
    Storage::fake('local');
});

describe('download', function (): void {
    it('streams the file as an attachment with an encoded file name and the security headers, without logging in', function (): void {
        $attachment = Attachment::factory()->ofType('application/pdf', 'pdf')->withFile('%PDF-1.4')->create(['name' => '季度 报告.pdf']);

        $response = $this->get(AttachmentResource::downloadUrl($attachment))->assertOk();

        expect($response->streamedContent())->toBe('%PDF-1.4');
        $response->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', "attachment; filename=attachment-{$attachment->id}.pdf; filename*=utf-8''%E5%AD%A3%E5%BA%A6%20%E6%8A%A5%E5%91%8A.pdf")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    });

    it('keeps the ASCII part of a file name and falls back only when nothing readable is left', function (string $name, string $fallback): void {
        $attachment = Attachment::factory()->ofType('application/pdf', 'pdf')->withFile()->create(['name' => $name]);
        $fallback = str_replace(':id', (string) $attachment->id, $fallback);

        $this->get(AttachmentResource::downloadUrl($attachment))
            ->assertOk()
            ->assertHeader('Content-Disposition', HeaderUtils::makeDisposition('attachment', $name, $fallback));
    })->with([
        'Chinese only' => ['季度报告.pdf', 'attachment-:id.pdf'],
        'Chinese with spaces and punctuation' => ['《季度 报告》.pdf', 'attachment-:id.pdf'],
        'accented Latin' => ['Résumé.pdf', 'Resume.pdf'],
        'ASCII' => ['report-q3.pdf', 'report-q3.pdf'],
    ]);

    it('lets the browser cache a download privately until the URL expires', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:10:00'));
        $attachment = Attachment::factory()->withFile()->create();

        $this->get(AttachmentResource::downloadUrl($attachment))->assertHeader('Cache-Control', 'max-age=3000, private');
    });

    it('downloads an SVG as an attachment instead of rendering it', function (): void {
        $attachment = Attachment::factory()->ofType('image/svg+xml', 'svg')->withFile('<svg><script>alert(1)</script></svg>')->create();

        $this->get(AttachmentResource::downloadUrl($attachment))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename='.$attachment->name);
    });

    it('rejects an expired URL', function (): void {
        $attachment = Attachment::factory()->withFile()->create();
        $url = AttachmentResource::downloadUrl($attachment);

        $this->travel(61)->minutes();

        $this->get($url)->assertForbidden()->assertJsonPath('code', 403);
    });

    it('rejects a URL without a signature, with a tampered signature or for another attachment', function (): void {
        $attachment = Attachment::factory()->withFile()->create();
        $other = Attachment::factory()->withFile()->create();
        $url = AttachmentResource::downloadUrl($attachment);

        $this->get("/api/admin/attachments/{$attachment->id}/download")->assertForbidden();
        $this->get($url.'0')->assertForbidden();
        $this->get(str_replace("/attachments/{$attachment->id}/", "/attachments/{$other->id}/", $url))->assertForbidden();
    });

    it('returns 404 when the file is gone from the disk', function (): void {
        $attachment = Attachment::factory()->create();

        $this->get(AttachmentResource::downloadUrl($attachment))->assertNotFound();
    });
});

describe('signed URLs', function (): void {
    it('signs the same URL within a time window and a new one in the next window', function (): void {
        $attachment = Attachment::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:00:00'));
        $first = AttachmentResource::downloadUrl($attachment);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:29:59'));
        expect(AttachmentResource::downloadUrl($attachment))->toBe($first);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:30:00'));
        expect(AttachmentResource::downloadUrl($attachment))->not->toBe($first);
    });

    it('keeps a URL valid for at least one window, at most two', function (string $signedAt, string $expiresAt): void {
        $this->travelTo(CarbonImmutable::parse($signedAt));

        expect(AttachmentResource::urlExpiresAt()->toIso8601String())->toBe(CarbonImmutable::parse($expiresAt)->toIso8601String())
            ->and(now()->diffInHours(AttachmentResource::avatarUrlExpiresAt()))->toBeGreaterThanOrEqual(24)->toBeLessThanOrEqual(48);
    })->with([
        'start of a window' => ['2026-11-20 08:00:00', '2026-11-20 09:00:00'],
        'end of a window' => ['2026-11-20 08:29:59', '2026-11-20 09:00:00'],
    ]);

    it('accepts a URL until the end of the next window and rejects it afterwards', function (): void {
        Storage::disk('local')->put('a.txt', 'x');
        $attachment = Attachment::factory()->ofType('text/plain', 'txt')->create(['path' => 'a.txt']);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:29:59'));
        $url = AttachmentResource::downloadUrl($attachment);

        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:59:59'));
        $this->get($url)->assertOk();

        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:00:01'));
        $this->get($url)->assertForbidden();
    });
});

describe('preview', function (): void {
    it('shows an image inline with the security headers and a private cache until the URL expires', function (string $mimeType, string $extension): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-20 08:10:00'));
        $attachment = Attachment::factory()->ofType($mimeType, $extension)->withFile(pngBytes())->create(['name' => "photo.{$extension}"]);

        $this->get(AttachmentResource::previewUrl($attachment) ?? '')
            ->assertOk()
            ->assertHeader('Content-Type', $mimeType)
            ->assertHeader('Content-Disposition', "inline; filename=photo.{$extension}")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'max-age=3000, private');
    })->with([
        ['image/jpeg', 'jpg'],
        ['image/png', 'png'],
        ['image/gif', 'gif'],
        ['image/webp', 'webp'],
    ]);

    it('has no preview for anything but the image whitelist, SVG included', function (string $mimeType, string $extension): void {
        $attachment = Attachment::factory()->ofType($mimeType, $extension)->withFile('<svg><script>alert(1)</script></svg>')->create();
        $signedPreview = str_replace('/download?', '/preview?', AttachmentResource::downloadUrl($attachment));

        expect(AttachmentResource::previewUrl($attachment))->toBeNull();

        $this->get($signedPreview)->assertForbidden();
        $this->get(URL::temporarySignedRoute('admin.attachments.preview', now()->addMinute(), ['attachment' => $attachment->id]))->assertNotFound();
    })->with([
        ['image/svg+xml', 'svg'],
        ['text/html', 'html'],
        ['application/pdf', 'pdf'],
    ]);

    it('rejects an expired URL', function (): void {
        $attachment = Attachment::factory()->withFile(pngBytes())->create();
        $url = AttachmentResource::previewUrl($attachment) ?? '';

        $this->travel(61)->minutes();

        $this->get($url)->assertForbidden();
    });
});
