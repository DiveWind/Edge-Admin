<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\{HeaderUtils, StreamedResponse};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 预览附件（GET /attachments/{attachment}/preview，设计文档 §6.8）：签名的临时 URL，规则同下载。
 *
 * 只放行图片白名单（Attachment::PREVIEWABLE_MIME_TYPES：jpeg、png、gif、webp）以 inline 输出；其他类型（含 SVG、HTML、PDF）404。
 * 带 nosniff 与 CSP sandbox；浏览器可缓存到地址过期为止（`private, max-age=<剩余秒数>`）。
 */
class AttachmentPreviewController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk($attachment->disk);

        if (! $attachment->isPreviewable() || ! $disk->exists($attachment->path)) {
            throw new NotFoundHttpException;
        }

        $maxAge = max(0, $request->integer('expires') - now()->getTimestamp());

        return $disk->response($attachment->path, $attachment->name, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $attachment->name, $attachment->asciiFileName()),
            ...AttachmentDownloadController::SECURITY_HEADERS,
            'Cache-Control' => "private, max-age={$maxAge}",
        ]);
    }
}
