<?php

namespace App\Http\Admin\Monitor\Requests;

use App\Http\Admin\Requests\ListRequest;

/**
 * 定时任务列表的查询参数（GET /monitor/schedules，设计文档 §6.3、§6.6）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式（name、type）；其他筛选与排序参数由 query-builder 的白名单拒绝（422）。
 */
class ScheduleIndexRequest extends ListRequest
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
            'filter.type' => ['sometimes', 'string', 'in:command,job,shell'],
        ];
    }
}
