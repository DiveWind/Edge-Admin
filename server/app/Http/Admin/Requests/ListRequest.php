<?php

namespace App\Http\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 后台列表接口的查询参数（设计文档 §6.3）。各模块的列表请求继承本类，在 rules() 中合并 parent::rules() 并追加筛选字段：
 *
 * - 分页：`page`（从 1 开始）、`pageSize`（缺省与上限见 config('core.apps.admin.pagination')，超过上限返回 422）；
 * - 排序：`sort`，多个字段逗号分隔，降序加 `-`，如 `sort=-created_at,id`；
 * - 筛选：`filter[字段]`，如 `filter[status]=active`。
 *
 * 允许的排序、筛选字段由后续接入的 spatie/laravel-query-builder 显式白名单控制；这里只校验参数格式。
 * 控制器：`ArticleResource::collection($query->paginate($request->pageSize()))`，响应由 FormatResponse 转为 `{ items, total }`。
 */
class ListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'pageSize' => ['sometimes', 'integer', 'min:1', 'max:'.self::maxPageSize()],
            'sort' => ['sometimes', 'string', 'regex:/^-?[A-Za-z0-9_.]+(,-?[A-Za-z0-9_.]+)*$/'],
            'filter' => ['sometimes', 'array'],
        ];
    }

    /**
     * 当前页码（从 1 开始）。
     */
    public function page(): int
    {
        return $this->integer('page', 1);
    }

    /**
     * 每页条数：未传时取默认值；上限已由 rules() 校验。
     */
    public function pageSize(): int
    {
        return $this->integer('pageSize', config()->integer('core.apps.admin.pagination.page_size'));
    }

    /**
     * pageSize 的上限。
     */
    public static function maxPageSize(): int
    {
        return config()->integer('core.apps.admin.pagination.max_page_size');
    }
}
