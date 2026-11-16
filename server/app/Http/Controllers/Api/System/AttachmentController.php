<?php

namespace App\Http\Controllers\Api\System;

use App\Actions\System\DeleteAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\AttachmentIndexRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 附件管理（上传入口为 POST /api/uploads）。
 */
class AttachmentController extends Controller
{
    public function index(AttachmentIndexRequest $request): AnonymousResourceCollection
    {
        $query = Attachment::query()->with('uploader')->filter($request->filters());

        return AttachmentResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    /**
     * 同时删除物理文件。
     */
    public function destroy(Attachment $attachment, DeleteAttachment $deleteAttachment): Response
    {
        $deleteAttachment->handle($attachment);

        return response()->noContent();
    }
}
