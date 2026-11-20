<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\DictType;
use App\Support\Database\UniqueConstraint;
use Illuminate\Validation\ValidationException;

/**
 * 新增字典类型（POST /system/dict-types，设计文档 §6.7）：自定义类型（is_builtin 为 false，内置类型只由 core:sync-dicts 新增）。
 *
 * - code 唯一：表单已校验，并发冲突时同样返回 422（code，UniqueConstraint）；
 * - 未提交的字段取列默认值：status 为 enabled。
 */
class CreateDictType
{
    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（DictType 的 `#[Fillable]`，其余键忽略）
     *
     * @throws ValidationException
     */
    public function handle(array $attributes): DictType
    {
        $type = new DictType;
        $type->fill(array_intersect_key($attributes, array_flip($type->getFillable())));
        $type->is_builtin = false;

        UniqueConstraint::save($type, ['code' => __('Code')]);

        return $type->refresh();
    }
}
