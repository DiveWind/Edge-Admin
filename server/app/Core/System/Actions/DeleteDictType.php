<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\DictType;
use Illuminate\Support\Facades\DB;

/**
 * 删除字典类型（DELETE /system/dict-types/{dict_type}，设计文档 §6.7、§8.6）：连同其下的字典项一起删除。
 *
 * - 内置类型不能删除由 DictTypePolicy 判定（403）；
 * - 字典项完全从属于类型，级联删除（先删字典项，外键 cascadeOnDelete 兜底）；内置字典项只会出现在内置类型下，
 *   因此不会绕过“内置字典项不能删”；
 * - 字典缓存在事务提交后清除（DictionaryObserver）。
 */
class DeleteDictType
{
    /**
     * Execute the action.
     */
    public function handle(DictType $type): void
    {
        DB::transaction(function () use ($type): void {
            $type->items()->delete();
            $type->delete();
        });
    }
}
