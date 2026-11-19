<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Attachment, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * 保存上传的文件并登记为附件（POST /attachments，设计文档 §6.8）：文件已由 AllowedUpload 校验类型与大小。
 *
 * - 存到 config('core.attachments.disk')（默认私有的 local 盘），路径 `attachments/<年>/<月>/<日>/<40 位随机串>.<扩展名>`：
 *   随机串与扩展名来自框架的 hashName()（扩展名由服务端探测的 MIME 推断），不使用客户端的文件名，没有路径穿越；
 * - mime_type、extension 为服务端探测的结果（不信任客户端的 Content-Type 与扩展名），另记 size、sha256；
 * - 原始文件名只保存在 name（最长 255 字符），用于显示与下载时的文件名；
 * - 附件未绑定（孤儿），created_by 为上传者；不按 sha256 去重（每次上传都是新记录、新文件）；
 * - 写记录失败时删除已保存的文件。
 */
class StoreAttachment
{
    /**
     * Execute the action.
     *
     * @throws RuntimeException 文件保存失败
     */
    public function handle(UploadedFile $file, User $uploader): Attachment
    {
        $disk = config()->string('core.attachments.disk');
        $path = Storage::disk($disk)->putFile(Attachment::DIRECTORY.'/'.now()->format('Y/m/d'), $file);

        if (! is_string($path)) {
            throw new RuntimeException("附件保存到存储盘 [{$disk}] 失败。");
        }

        try {
            $attachment = new Attachment([
                'disk' => $disk,
                'path' => $path,
                'name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'extension' => pathinfo($path, PATHINFO_EXTENSION),
                'size' => (int) $file->getSize(),
                'sha256' => (string) hash_file('sha256', $file->getRealPath()),
            ]);
            $attachment->created_by = $uploader->id;
            $attachment->save();
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        return $attachment;
    }
}
