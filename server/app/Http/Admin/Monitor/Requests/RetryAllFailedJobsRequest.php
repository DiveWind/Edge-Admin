<?php

namespace App\Http\Admin\Monitor\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 全部重试（POST /monitor/failed-jobs/retry-all，设计文档 §6.6）：`queue` 可选，只重试该队列的失败任务；不提交时重试全部。
 *
 * 只做输入格式校验；未声明的字段一律拒绝（AppServiceProvider：failOnUnknownFields）；授权写在控制器的 #[Authorize] 上。
 */
class RetryAllFailedJobsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'queue' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
