<?php

namespace App\Http\Admin\Content\Requests;

use App\Core\System\Enums\ExportFormat;

/**
 * 导出文章列表（POST /content/articles/export，设计文档 §6.10、§6.11）：`{ format, filter, sort }`。
 *
 * 继承文章列表的请求：filter、sort 的格式与值校验与列表完全相同（允许的筛选与排序由 ArticleList 的白名单决定，其他参数返回 422）；
 * 去掉分页参数 page、pageSize（提交即 422，未声明字段），另加 format（xlsx、csv，默认 xlsx）。
 */
class ExportArticlesRequest extends ArticleIndexRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['page'], $rules['pageSize']);

        return [
            ...$rules,
            'format' => ['sometimes', 'string', 'in:'.implode(',', array_column(ExportFormat::cases(), 'value'))],
        ];
    }

    /**
     * 文件格式（默认 xlsx）。
     */
    public function exportFormat(): ExportFormat
    {
        return $this->enum('format', ExportFormat::class) ?? ExportFormat::Xlsx;
    }
}
