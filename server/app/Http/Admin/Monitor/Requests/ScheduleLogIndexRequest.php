<?php

namespace App\Http\Admin\Monitor\Requests;

use App\Http\Admin\Requests\ListRequest;

/**
 * 执行记录列表的查询参数（GET /monitor/schedules/{schedule}/logs，设计文档 §6.3、§6.6）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；type 可用逗号传多个（starting、finished、failed、skipped），created_at 区间由 DateTimeRangeFilter 校验。
 */
class ScheduleLogIndexRequest extends ListRequest
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
            'filter.type' => ['sometimes', 'string', 'regex:/^(starting|finished|failed|skipped)(,(starting|finished|failed|skipped))*$/'],
            'filter.created_at' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
