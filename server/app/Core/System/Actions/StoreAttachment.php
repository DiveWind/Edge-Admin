<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Attachment, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{File, UploadedFile};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * 保存文件并登记为附件（设计文档 §6.8）：附件元数据的规则只有这一份，上传（POST /attachments，文件已由 AllowedUpload 校验类型与大小）
 * 与导出文件（GenerateExport）都经过它。
 *
 * - 存到 config('core.system.attachment.disk')（默认私有的 local 盘），路径 `<目录>/<年>/<月>/<日>/<40 位随机串>.<扩展名>`（目录默认 `attachments`）：
 *   随机串由服务端生成；未指定扩展名时由服务端探测的 MIME 推断（框架的 hashName()）。不使用客户端的文件名，没有路径穿越；
 * - mime_type 未指定时为服务端探测的结果（不信任客户端的 Content-Type 与扩展名）；extension 取自保存的路径；另记 size、sha256；
 * - 显示名 name（最长 255 字符）：未指定时为上传的原始文件名，用于显示与下载时的文件名；
 * - created_by 为 $owner；指定 $attachable 时直接绑定到它的 $collection 用途，否则是未绑定的附件（孤儿，等待 SyncAttachments 绑定）；
 *   不按 sha256 去重（每次都是新记录、新文件）；
 * - 写记录失败时删除已保存的文件。在调用方的事务中调用时（嵌套为保存点），调用方之后回滚的，由调用方删除文件（$attachment->path）。
 */
class StoreAttachment
{
    /**
     * Execute the action.
     *
     * @param  string|null  $name  显示名；null 时为上传的原始文件名（File 为文件名）
     * @param  string|null  $extension  保存的扩展名；null 时按探测的 MIME 推断
     * @param  string|null  $mimeType  MIME；null 时为服务端探测的结果
     *
     * @throws RuntimeException 文件保存失败
     * @throws Throwable 写记录失败（文件已删除）
     */
    public function handle(
        UploadedFile|File $file,
        User $owner,
        ?Model $attachable = null,
        ?string $collection = null,
        ?string $name = null,
        ?string $extension = null,
        ?string $mimeType = null,
        string $directory = Attachment::DIRECTORY,
    ): Attachment {
        $disk = config()->string('core.system.attachment.disk');
        $directory .= '/'.now()->format('Y/m/d');
        $path = $extension === null
            ? Storage::disk($disk)->putFile($directory, $file)
            : Storage::disk($disk)->putFileAs($directory, $file, Str::random(40).'.'.$extension);

        if (! is_string($path)) {
            throw new RuntimeException("附件保存到存储盘 [{$disk}] 失败。");
        }

        try {
            return DB::transaction(function () use ($file, $owner, $attachable, $collection, $name, $mimeType, $disk, $path): Attachment {
                $attachment = new Attachment([
                    'disk' => $disk,
                    'path' => $path,
                    'name' => mb_substr($name ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename()), 0, 255),
                    'mime_type' => $mimeType ?? $file->getMimeType() ?? 'application/octet-stream',
                    'extension' => pathinfo($path, PATHINFO_EXTENSION),
                    'size' => (int) $file->getSize(),
                    'sha256' => (string) hash_file('sha256', $file->getRealPath()),
                ]);
                $attachment->created_by = $owner->id;

                if ($attachable instanceof Model) {
                    $attachment->attachable()->associate($attachable);
                    $attachment->collection = $collection;
                }

                $attachment->save();

                return $attachment;
            });
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }
}
