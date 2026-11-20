<?php

namespace App\Http\Admin\Message\Requests;

use App\Core\Message\Enums\{AnnouncementStatus, AnnouncementTarget, NotificationLevel};
use App\Http\Admin\Requests\ListRequest;

/**
 * 公告管理列表的查询参数（GET /message/announcements，设计文档 §6.3、§6.9）：page、pageSize、sort 由 ListRequest 校验，
 * 这里校验筛选值的格式；允许的筛选（title、status、level、target、created_at、published_at）与排序字段由模块 Query 类（<模型>List）中 query-builder 的白名单决定（两边一致由 tests/Arch 检查）。
 */
class AnnouncementIndexRequest extends ListRequest
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
            'filter.status' => ['sometimes', 'string', 'in:'.implode(',', array_column(AnnouncementStatus::cases(), 'value'))],
            'filter.level' => ['sometimes', 'string', 'in:'.implode(',', array_column(NotificationLevel::cases(), 'value'))],
            'filter.target' => ['sometimes', 'string', 'in:'.implode(',', array_column(AnnouncementTarget::cases(), 'value'))],
            'filter.created_at' => ['sometimes', 'string', 'max:100'],
            'filter.published_at' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
