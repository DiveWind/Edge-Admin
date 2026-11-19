<?php

namespace App\Core\System\Data;

/**
 * 模块声明的一个初始字典项（DictTypeData::$items 的元素，设计文档 §6.7）。
 */
final readonly class DictItemData
{
    /**
     * @param  string  $value  值（同一类型内唯一）
     * @param  string  $label  显示名（前端 i18n key 或纯文本）
     * @param  ?string  $color  给前端 Tag 用的颜色
     * @param  int  $sort  排序（未声明时为声明中的序号）
     */
    public function __construct(
        public string $value,
        public string $label,
        public ?string $color = null,
        public int $sort = 0,
    ) {}
}
