<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{DictItem, DictType};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * 新增字典项（POST /system/dict-types/{dict_type}/items，设计文档 §6.7）：自定义字典项（is_builtin 为 false，内置项只由 core:sync-dicts 新增）。
 *
 * - 同一类型内 value 唯一：表单已校验，并发冲突时同样返回 422（value）；
 * - 未提交的字段取列默认值：sort 为 0，status 为 enabled。
 */
class CreateDictItem
{
    /**
     * 可写入的字段（其余键忽略；所属类型由路径决定，不能修改）。
     *
     * @var list<string>
     */
    public const array ATTRIBUTES = ['value', 'label', 'color', 'sort', 'status'];

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（ATTRIBUTES）
     *
     * @throws ValidationException
     */
    public function handle(DictType $type, array $attributes): DictItem
    {
        $item = new DictItem(array_intersect_key($attributes, array_flip(self::ATTRIBUTES)));
        $item->type()->associate($type);
        $item->is_builtin = false;

        self::save($item);

        return $item->refresh();
    }

    /**
     * 保存字典项；value 重复（并发写入时数据库的唯一索引）返回 422（value）。
     *
     * @throws ValidationException
     */
    public static function save(DictItem $item): void
    {
        try {
            $item->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'value' => [__('validation.unique', ['attribute' => __('Value')])],
            ]);
        }
    }
}
