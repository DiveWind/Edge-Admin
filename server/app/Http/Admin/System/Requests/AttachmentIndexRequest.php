<?php

namespace App\Http\Admin\System\Requests;

use App\Http\Admin\Requests\ListRequest;

/**
 * 附件管理列表的查询参数（GET /system/attachments，设计文档 §6.3、§6.8）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选（name、mime_type、created_by、collection、is_bound、created_at）与排序字段由控制器中
 * query-builder 的白名单决定，其他参数返回 422。created_at 区间（`起,止`）的格式由 DateTimeRangeFilter 校验。
 */
class AttachmentIndexRequest extends ListRequest
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
            'filter.mime_type' => ['sometimes', 'string', 'max:255'],
            'filter.created_by' => ['sometimes', 'integer'],
            'filter.collection' => ['sometimes', 'string', 'max:64'],
            'filter.is_bound' => ['sometimes', 'in:true,false,1,0'],
            'filter.created_at' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
