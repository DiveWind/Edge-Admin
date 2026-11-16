<?php

namespace App\Http\Controllers\Api\Options;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;

/**
 * 公开的系统参数（is_public）：{data: {key: value}}，无需登录（登录页使用）。
 */
class SettingOptionController extends Controller
{
    public function __invoke(Settings $settings): JsonResponse
    {
        return response()->json(['data' => (object) $settings->public()]);
    }
}
