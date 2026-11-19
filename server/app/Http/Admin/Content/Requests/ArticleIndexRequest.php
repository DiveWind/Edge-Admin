<?php

namespace App\Http\Admin\Content\Requests;

use App\Http\Admin\Requests\ListRequest;
use App\Modules\Content\Enums\ArticleStatus;

/**
 * 文章列表的查询参数（GET /content/articles，设计文档 §6.3、§6.11）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选（title、category、status、published_at、created_at、trashed）与排序字段由
 * ArticleList 中 query-builder 的白名单决定，其他参数返回 422。时间区间（`起,止`）的格式由 DateTimeRangeFilter 校验（422，`filter.<字段>`）。
 *
 * filter[trashed]：`with`（含已删除）或 `only`（只看已删除），其他值 422；带它时还要 viewTrashed 的授权（ArticleList，403）。
 * category 只校验格式：按已停用的分类筛选旧文章是合理的，不要求是启用的字典项。
 */
class ArticleIndexRequest extends ListRequest
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
            'filter.title' => ['sometimes', 'string', 'max:255'],
            'filter.category' => ['sometimes', 'string', 'max:64'],
            'filter.status' => ['sometimes', 'string', 'in:'.implode(',', array_column(ArticleStatus::cases(), 'value'))],
            'filter.published_at' => ['sometimes', 'string', 'max:100'],
            'filter.created_at' => ['sometimes', 'string', 'max:100'],
            'filter.trashed' => ['sometimes', 'string', 'in:with,only'],
        ];
    }
}
