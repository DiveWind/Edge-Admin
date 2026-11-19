<?php

namespace App\Http\Admin\Monitor\Requests;

use App\Http\Admin\Middleware\RecordOperationLog;
use App\Http\Admin\Requests\ListRequest;

/**
 * 操作日志列表的查询参数（GET /monitor/operation-logs，设计文档 §6.3、§6.6）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选与排序字段由控制器中 query-builder 的白名单决定，其他参数返回 422。
 * created_at 区间（`起,止`）的格式由 DateTimeRangeFilter 校验（422，`filter.created_at`）。
 */
class OperationLogIndexRequest extends ListRequest
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
            'filter.method' => ['sometimes', 'string', 'in:'.implode(',', RecordOperationLog::METHODS)],
            'filter.status' => ['sometimes', 'integer', 'between:100,599'],
            'filter.request_id' => ['sometimes', 'string', 'max:255'],
            'filter.username' => ['sometimes', 'string', 'max:255'],
            'filter.description' => ['sometimes', 'string', 'max:255'],
            'filter.ip_address' => ['sometimes', 'string', 'max:45'],
            'filter.created_at' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
