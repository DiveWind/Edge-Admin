<?php

namespace App\Http\Admin\Message\Requests;

use App\Http\Admin\Requests\ListRequest;

/**
 * 我的通知列表的查询参数（GET /notifications，设计文档 §6.3、§6.9）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选（is_read、type、created_at）与排序字段由控制器中 query-builder 的白名单决定，其他参数返回 422。
 *
 * - is_read：`true` / `false`（或 `1` / `0`）；
 * - type：通知的对外类型名，可用逗号传多个（`message.announcement,content.article_approved`）；
 * - created_at：时间区间（`起,止`，DateTimeRangeFilter 校验）。
 */
class NotificationIndexRequest extends ListRequest
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
            'filter.is_read' => ['sometimes', 'string', 'in:true,false,1,0'],
            'filter.type' => ['sometimes', 'string', 'max:1000', 'regex:/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+(,[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+)*$/'],
        ];
    }
}
