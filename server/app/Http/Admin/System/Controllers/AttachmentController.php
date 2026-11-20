<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\DeleteUnboundAttachment;
use App\Core\System\Models\{Attachment, User};
use App\Core\System\Queries\AttachmentList;
use App\Http\Admin\System\Requests\AttachmentIndexRequest;
use App\Http\Admin\System\Resources\AttachmentResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 附件管理（`/system/attachments`，设计文档 §6.8）：列表与删除。上传与详情是个人功能（`/attachments`，MyAttachmentController）。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 AttachmentPolicy 判定
 * （system.attachment、system.attachment.delete 与数据范围），删除调用 System 模块的 Action。
 */
class AttachmentController
{
    /**
     * 列表（经过数据范围 visibleTo($actor)，不含私有附件如导出的文件，预加载 creator）：筛选 name、mime_type（模糊）、created_by、collection（精确）、
     * is_bound（true / false）、created_at（区间 `起,止`）；排序 id、size、created_at，默认 `-id`。不在白名单内的参数返回 422（§6.3）。
     */
    #[Authorize('viewAny', Attachment::class)]
    public function index(AttachmentIndexRequest $request, AttachmentList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return AttachmentResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 删除（连同文件，事务提交后）：已绑定的附件 403（Policy）；并发时（检查后被绑定）在锁内复核 → 422（Action）。
     */
    #[Authorize('delete', 'attachment')]
    public function destroy(Attachment $attachment, DeleteUnboundAttachment $action): void
    {
        $action->handle($attachment);
    }
}
