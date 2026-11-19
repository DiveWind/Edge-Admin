<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\DictItem;

/**
 * 删除字典项（DELETE /system/dict-items/{dict_item}，设计文档 §6.7、§8.6）。
 * 内置字典项不能删除由 DictItemPolicy 判定（403）；字典缓存在删除后清除（DictionaryObserver）。
 */
class DeleteDictItem
{
    /**
     * Execute the action.
     */
    public function handle(DictItem $item): void
    {
        $item->delete();
    }
}
