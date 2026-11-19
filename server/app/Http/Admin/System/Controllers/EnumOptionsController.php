<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Services\EnumRegistry;
use App\Http\Admin\System\Requests\EnumOptionsRequest;

/**
 * 枚举选项（GET /options/enums?names=a,b，设计文档 §6.7）：`{ 名称: [{ value, label, color? }] }`（按请求顺序）。
 *
 * - 只下发在 EnumRegistry 中登记过的名称（显式白名单），未登记的名称 422；
 * - label 按 Accept-Language 翻译（HasLabel::label()）；枚举实现了 HasColor 时另有 color；
 * - 登录即可访问（tests/Arch 的 LOGIN_ONLY_ROUTES）。
 */
class EnumOptionsController
{
    /**
     * Handle the incoming request.
     *
     * @return array<string, list<array{value: int|string, label: string, color?: string}>>
     */
    public function __invoke(EnumOptionsRequest $request, EnumRegistry $enums): array
    {
        $options = [];

        foreach ($request->names() as $name) {
            $options[$name] = $enums->options($name);
        }

        return $options;
    }
}
