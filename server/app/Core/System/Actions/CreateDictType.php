<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\DictType;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * 新增字典类型（POST /system/dict-types，设计文档 §6.7）：自定义类型（is_builtin 为 false，内置类型只由 core:sync-dicts 新增）。
 *
 * - code 唯一：表单已校验，并发冲突时同样返回 422（code）；
 * - 未提交的字段取列默认值：status 为 enabled。
 */
class CreateDictType
{
    /**
     * 可写入的字段（其余键忽略）。
     *
     * @var list<string>
     */
    public const array ATTRIBUTES = ['code', 'name', 'status', 'remark'];

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（ATTRIBUTES）
     *
     * @throws ValidationException
     */
    public function handle(array $attributes): DictType
    {
        $type = new DictType(array_intersect_key($attributes, array_flip(self::ATTRIBUTES)));
        $type->is_builtin = false;

        self::save($type);

        return $type->refresh();
    }

    /**
     * 保存类型；code 重复（并发写入时数据库的唯一索引）返回 422（code）。
     *
     * @throws ValidationException
     */
    public static function save(DictType $type): void
    {
        try {
            $type->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => [__('validation.unique', ['attribute' => __('Code')])],
            ]);
        }
    }
}
