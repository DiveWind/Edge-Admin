<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{DictItem, DictType};
use App\Support\Database\UniqueConstraint;
use Illuminate\Validation\ValidationException;

/**
 * 新增字典项（POST /system/dict-types/{dict_type}/items，设计文档 §6.7）：自定义字典项（is_builtin 为 false，内置项只由 core:sync-dicts 新增）。
 *
 * - 同一类型内 value 唯一：表单已校验，并发冲突时同样返回 422（value，UniqueConstraint）；
 * - 未提交的字段取列默认值：sort 为 0，status 为 enabled。
 */
class CreateDictItem
{
    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（DictItem 的 `#[Fillable]`，其余键忽略）
     *
     * @throws ValidationException
     */
    public function handle(DictType $type, array $attributes): DictItem
    {
        $item = new DictItem;
        $item->fill(array_intersect_key($attributes, array_flip($item->getFillable())));
        $item->type()->associate($type);
        $item->is_builtin = false;

        UniqueConstraint::save($item, ['value' => __('Value')]);

        return $item->refresh();
    }
}
