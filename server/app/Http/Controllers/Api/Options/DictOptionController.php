<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Http\Requests\DictOptionRequest;
use App\Services\Dictionary;
use Illuminate\Http\JsonResponse;

/**
 * 批量获取数据字典启用项：{data: {code: [{label, value, color}]}}，按 sort 排序，带缓存，登录即可访问。
 */
class DictOptionController extends Controller
{
    public function __invoke(DictOptionRequest $request, Dictionary $dictionary): JsonResponse
    {
        return response()->json(['data' => (object) $dictionary->many($request->codes())]);
    }
}
