<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\DictItem;
use App\Support\Database\UniqueConstraint;
use Illuminate\Validation\ValidationException;

/**
 * 修改字典项（PUT /system/dict-items/{dict_item}，设计文档 §6.7、§8.6）：
 *
 * - 内置字典项的 value 不能修改 → 422（value）：InDict 与业务代码按它判断，只能由 core:sync-dicts 维护；
 *   label、color、sort、status 可以修改，再次同步不会覆盖；
 * - 同一类型内 value 唯一 → 422（value，UniqueConstraint，含并发冲突）；所属类型不能修改。
 */
class UpdateDictItem
{
    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（未提交的保持不变）
     *
     * @throws ValidationException
     */
    public function handle(DictItem $item, array $attributes): DictItem
    {
        $item->fill(array_intersect_key($attributes, array_flip($item->getFillable())));

        if ($item->is_builtin && $item->isDirty('value')) {
            throw ValidationException::withMessages([
                'value' => [__('The value of a built-in dictionary item cannot be changed.')],
            ]);
        }

        UniqueConstraint::save($item, ['value' => __('Value')]);

        return $item;
    }
}
