<?php

namespace App\Http\Admin\System\Requests;

use App\Core\System\Enums\{ExportFormat, ExportStatus};
use App\Http\Admin\Requests\ListRequest;

/**
 * 我的导出列表的查询参数（GET /exports，设计文档 §6.3、§6.10）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选（name、status、format、created_at）与排序字段由模块 Query 类（<模型>List）中 query-builder 的白名单决定（两边一致由 tests/Arch 检查），其他参数返回 422。
 */
class ExportIndexRequest extends ListRequest
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
            'filter.name' => ['sometimes', 'string', 'max:128'],
            'filter.status' => ['sometimes', 'string', 'in:'.implode(',', array_column(ExportStatus::cases(), 'value'))],
            'filter.format' => ['sometimes', 'string', 'in:'.implode(',', array_column(ExportFormat::cases(), 'value'))],
            'filter.created_at' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
