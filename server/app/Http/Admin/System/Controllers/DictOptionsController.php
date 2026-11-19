<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Services\Dictionary;
use App\Http\Admin\System\Requests\DictOptionsRequest;

/**
 * 字典选项（GET /options/dicts?types=a,b，设计文档 §6.7）：给表单下拉用，`{ 类型: [{ value, label, color }] }`（按请求顺序）。
 *
 * - 只含启用类型下的启用项，按 sort、id 排序；不存在或已停用的类型为 `[]`；读取 Dictionary 的缓存；
 * - 登录即可访问（tests/Arch 的 LOGIN_ONLY_ROUTES）：字典是各模块表单共用的参考数据，不按权限码控制；
 *   只输出 value、label、color，敏感的值列表不应做成字典。
 */
class DictOptionsController
{
    /**
     * Handle the incoming request.
     *
     * @return array<string, list<array{value: string, label: string, color: ?string}>>
     */
    public function __invoke(DictOptionsRequest $request, Dictionary $dictionary): array
    {
        return $dictionary->many($request->types());
    }
}
