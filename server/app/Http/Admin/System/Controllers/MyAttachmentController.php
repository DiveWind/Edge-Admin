<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\StoreAttachment;
use App\Core\System\Models\{Attachment, User};
use App\Http\Admin\System\Requests\MyAttachmentRequest;
use App\Http\Admin\System\Resources\AttachmentResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LogicException;

/**
 * 个人的附件（`/attachments`，设计文档 §6.8）：上传与详情，各业务表单使用。附件管理（`/system/attachments`）见 AttachmentController；
 * 下载与预览是签名路由（AttachmentDownloadController、AttachmentPreviewController）。
 *
 * 授权由 AttachmentPolicy 判定：上传只需登录；详情的访问权委托给绑定对象（未绑定的只有上传者本人）。
 */
class MyAttachmentController
{
    /**
     * 上传（201）：返回未绑定的附件与签名的下载、预览地址；由业务表单提交它的 id 完成绑定。
     */
    #[Authorize('create', Attachment::class)]
    public function store(MyAttachmentRequest $request, StoreAttachment $action, #[CurrentUser] User $actor): AttachmentResource
    {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('file 已由 MyAttachmentRequest 校验为上传的文件。');
        }

        return new AttachmentResource($action->handle($file, $actor));
    }

    /**
     * 详情，含重新签发的下载、预览地址（访问权见 AttachmentPolicy::view()）。
     */
    #[Authorize('view', 'attachment')]
    public function show(Attachment $attachment): AttachmentResource
    {
        return new AttachmentResource($attachment);
    }
}
