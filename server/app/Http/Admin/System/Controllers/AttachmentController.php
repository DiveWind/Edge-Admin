<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\{DeleteUnboundAttachment, StoreAttachment};
use App\Core\System\Models\{Attachment, User};
use App\Http\Admin\System\Requests\{AttachmentIndexRequest, AttachmentUploadRequest};
use App\Http\Admin\System\Resources\AttachmentResource;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LogicException;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 附件（设计文档 §6.8）：上传与详情（`/attachments`，各业务表单使用），附件管理的列表与删除（`/system/attachments`）。
 * 下载与预览是签名路由（AttachmentDownloadController、AttachmentPreviewController）。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 AttachmentPolicy 判定
 * （上传只需登录；详情的访问权委托给绑定对象；管理按 system.attachment 与数据范围），写入调用 System 模块的 Action。
 */
class AttachmentController
{
    /**
     * 附件管理的列表（经过数据范围 visibleTo($actor)，不含私有附件如导出的文件，预加载 creator）：筛选 name、mime_type（模糊）、created_by、collection（精确）、
     * is_bound（true / false）、created_at（区间 `起,止`）；排序 id、size、created_at，默认 `-id`。不在白名单内的参数返回 422（§6.3）。
     */
    #[Authorize('viewAny', Attachment::class)]
    public function index(AttachmentIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $attachments = QueryBuilder::for(Attachment::query()->visibleTo($actor)->withoutPrivate()->with('creator'), $request)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('mime_type'),
                AllowedFilter::exact('created_by'),
                AllowedFilter::exact('collection'),
                AllowedFilter::callback('is_bound', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereNotNull('attachable_type')
                    : $query->whereNull('attachable_type')),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id', 'size', 'created_at')
            ->defaultSort('-id')
            ->paginate($request->pageSize());

        return AttachmentResource::collection($attachments);
    }

    /**
     * 上传（201）：返回未绑定的附件与签名的下载、预览地址；由业务表单提交它的 id 完成绑定。
     */
    #[Authorize('create', Attachment::class)]
    public function store(AttachmentUploadRequest $request, StoreAttachment $action, #[CurrentUser] User $actor): AttachmentResource
    {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            throw new LogicException('file 已由 AttachmentUploadRequest 校验为上传的文件。');
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

    /**
     * 删除（连同文件，事务提交后）：已绑定的附件 403（Policy）；并发时（检查后被绑定）在锁内复核 → 422（Action）。
     */
    #[Authorize('delete', 'attachment')]
    public function destroy(Attachment $attachment, DeleteUnboundAttachment $action): void
    {
        $action->handle($attachment);
    }
}
