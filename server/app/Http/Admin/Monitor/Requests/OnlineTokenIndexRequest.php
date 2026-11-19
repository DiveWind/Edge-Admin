<?php

namespace App\Http\Admin\Monitor\Requests;

use App\Http\Admin\Requests\ListRequest;

/**
 * 在线会话页 token 列表的查询参数（GET /monitor/online/tokens，设计文档 §6.3、§6.6）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式（user_id、username、name）；其他筛选与排序参数由 query-builder 的白名单拒绝（422）。
 */
class OnlineTokenIndexRequest extends ListRequest
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
            'filter.user_id' => ['sometimes', 'integer', 'min:1'],
            'filter.username' => ['sometimes', 'string', 'max:255'],
            'filter.name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
