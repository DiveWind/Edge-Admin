<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\Status;
use App\Http\Admin\Requests\ListRequest;

/**
 * 用户列表的查询参数（GET /system/users，设计文档 §6.3、§6.5）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选（username、name、email、status、created_at）与排序字段由模块 Query 类（<模型>List）中 query-builder 的白名单决定（两边一致由 tests/Arch 检查），
 * 其他参数返回 422。created_at 区间（`起,止`）的格式由 DateTimeRangeFilter 校验（422，`filter.created_at`）。
 */
class UserIndexRequest extends ListRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'filter.username' => ['sometimes', 'string', 'max:255'],
            'filter.name' => ['sometimes', 'string', 'max:255'],
            'filter.email' => ['sometimes', 'string', 'max:255'],
            'filter.status' => ['sometimes', 'string', 'in:'.implode(',', array_column(Status::cases(), 'value'))],
            'filter.created_at' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
