<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    /**
     * 上传图片到 public 磁盘（需执行 `php artisan storage:link`）。
     */
    public function __invoke(UploadRequest $request): JsonResponse
    {
        $disk = config()->string('edge.uploads.disk');
        $path = $request->file('file')->store('uploads/'.now()->format('Y/m'), $disk);

        if ($path === false) {
            abort(500, __('messages.upload_failed'));
        }

        return response()->json([
            'data' => [
                'url' => Storage::disk($disk)->url($path),
                'path' => $path,
            ],
        ], 201);
    }
}
