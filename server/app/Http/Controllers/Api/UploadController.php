<?php

namespace App\Http\Controllers\Api;

use App\Actions\System\StoreAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    /**
     * 上传文件到配置的磁盘并登记为附件（类型 / 大小见 config('edge.uploads')）。
     */
    public function __invoke(UploadRequest $request, #[CurrentUser] User $user, StoreAttachment $storeAttachment): JsonResponse
    {
        $attachment = $storeAttachment->handle($request->file('file'), $user);

        return (new AttachmentResource($attachment))->response()->setStatusCode(201);
    }
}
