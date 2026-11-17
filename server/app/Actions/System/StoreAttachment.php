<?php

namespace App\Actions\System;

use App\Models\{Attachment, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * 保存上传文件到配置的磁盘，并写一条附件记录；记录写入失败时删除已保存的文件。
 */
class StoreAttachment
{
    public function handle(UploadedFile $file, ?User $uploader): Attachment
    {
        $disk = config()->string('edge.uploads.disk');
        $path = $file->store('uploads/'.now()->format('Y/m'), $disk);

        if ($path === false) {
            throw new RuntimeException(__('messages.upload_failed'));
        }

        try {
            return Attachment::query()->create([
                'disk' => $disk,
                'path' => $path,
                'name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => (int) $file->getSize(),
                'uploaded_by' => $uploader?->getKey(),
            ]);
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }
}
