<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\Status;
use App\Http\Admin\Requests\ListRequest;

/**
 * 角色列表的查询参数（GET /system/roles，设计文档 §6.3、§6.5）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选（name、title、status）与排序字段由控制器中 query-builder 的白名单决定，其他参数返回 422。
 */
class RoleIndexRequest extends ListRequest
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
            'filter.name' => ['sometimes', 'string', 'max:255'],
            'filter.title' => ['sometimes', 'string', 'max:255'],
            'filter.status' => ['sometimes', 'string', 'in:'.implode(',', array_column(Status::cases(), 'value'))],
        ];
    }
}
