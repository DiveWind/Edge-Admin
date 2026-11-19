<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\DictType;
use Illuminate\Validation\ValidationException;

/**
 * 修改字典类型（PUT /system/dict-types/{dict_type}，设计文档 §6.7、§8.6）：
 *
 * - 内置类型的 code 不能修改 → 422（code）：InDict 与业务代码按它引用，只能由 core:sync-dicts 维护；
 *   名称、备注、状态可以修改，再次同步不会覆盖；
 * - code 唯一 → 422（code）。
 */
class UpdateDictType
{
    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（CreateDictType::ATTRIBUTES，未提交的保持不变）
     *
     * @throws ValidationException
     */
    public function handle(DictType $type, array $attributes): DictType
    {
        $type->fill(array_intersect_key($attributes, array_flip(CreateDictType::ATTRIBUTES)));

        if ($type->is_builtin && $type->isDirty('code')) {
            throw ValidationException::withMessages([
                'code' => [__('The code of a built-in dictionary type cannot be changed.')],
            ]);
        }

        CreateDictType::save($type);

        return $type;
    }
}
