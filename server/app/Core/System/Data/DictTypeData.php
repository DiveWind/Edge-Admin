<?php

namespace App\Core\System\Data;

/**
 * 模块声明的一个字典类型（DictRegistry 中注册、已校验的形式，设计文档 §6.7）。
 *
 * 同步命令 core:sync-dicts 按它写入 dict_types、dict_items：新增时全部字段生效；已有的类型与字典项只认标识
 * （code、value），名称、备注、label、color、sort、status 以数据库为准。
 */
final readonly class DictTypeData
{
    /**
     * @param  string  $code  类型编码（dict_types.code）
     * @param  string  $name  名称（前端 i18n key 或纯文本）
     * @param  array<string, DictItemData>  $items  初始字典项，以 value 为键（按声明顺序）
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $remark = null,
        public array $items = [],
    ) {}

    /**
     * 是否声明了该值的字典项。
     */
    public function hasItem(string $value): bool
    {
        return isset($this->items[$value]);
    }
}
