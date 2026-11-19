<?php

namespace App\Http\Admin\Monitor\Requests;

use App\Http\Admin\Requests\ListRequest;

/**
 * 失败任务列表的查询参数（GET /monitor/failed-jobs，设计文档 §6.3、§6.6）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式（queue、connection、exception、failed_at）；failed_at 区间由 DateTimeRangeFilter 校验（422，`filter.failed_at`）。
 */
class FailedJobIndexRequest extends ListRequest
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
            'filter.queue' => ['sometimes', 'string', 'max:255'],
            'filter.connection' => ['sometimes', 'string', 'max:255'],
            'filter.exception' => ['sometimes', 'string', 'max:255'],
            'filter.failed_at' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
