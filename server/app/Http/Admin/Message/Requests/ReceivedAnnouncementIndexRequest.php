<?php

namespace App\Http\Admin\Message\Requests;

use App\Core\Message\Enums\NotificationLevel;
use App\Http\Admin\Requests\ListRequest;

/**
 * 我收到的公告列表的查询参数（GET /announcements，设计文档 §6.3、§6.9）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选（title、level、published_at）与排序字段由模块 Query 类（<模型>List）中 query-builder 的白名单决定（两边一致由 tests/Arch 检查）。
 */
class ReceivedAnnouncementIndexRequest extends ListRequest
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
            'filter.level' => ['sometimes', 'string', 'in:'.implode(',', array_column(NotificationLevel::cases(), 'value'))],
            'filter.published_at' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
