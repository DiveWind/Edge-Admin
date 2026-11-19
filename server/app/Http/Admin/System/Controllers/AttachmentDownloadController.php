<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\{HeaderUtils, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 下载附件（GET /attachments/{attachment}/download，设计文档 §6.8）：签名的临时 URL（signed 中间件），不需要登录——
 * 授权在签发地址时已经完成（AttachmentResource 只在通过授权的接口中输出）。过期或签名不对 → 403。
 *
 * 一律 `Content-Disposition: attachment`（文件名按 RFC 6266 编码：`filename*` 为 UTF-8 原名，`filename` 为 ASCII 回退，见 Attachment::asciiFileName()），
 * 带 nosniff 与 CSP sandbox：浏览器不会内联执行 HTML、SVG。只允许私有缓存，max-age 不超过地址的剩余有效期。
 * 路由中只有附件 id，文件路径取自库中的记录，没有路径穿越。
 */
class AttachmentDownloadController
{
    /**
     * 下载与预览共用的安全响应头：不按内容猜测类型，内联打开时也不能执行脚本（与框架 storage.local 路由相同的 CSP）。
     *
     * @var array<string, string>
     */
    public const array SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
    ];

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            throw new NotFoundHttpException;
        }

        return $disk->download($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $attachment->name, $attachment->asciiFileName()),
            ...self::SECURITY_HEADERS,
            'Cache-Control' => $this->cacheControl($request),
        ]);
    }

    /**
     * 只允许浏览器私有缓存，max-age 不超过签名地址的剩余有效期（expires 已由 signed 中间件校验）。
     */
    private function cacheControl(Request $request): string
    {
        return 'private, max-age='.max(0, $request->integer('expires') - now()->getTimestamp());
    }
}
