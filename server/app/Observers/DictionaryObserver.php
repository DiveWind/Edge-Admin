<?php

namespace App\Observers;

use App\Services\Dictionary;

/**
 * 字典类型 / 字典项变更时清除字典缓存（DictType、DictItem 共用）。
 */
class DictionaryObserver
{
    public function __construct(protected Dictionary $dictionary) {}

    public function saved(): void
    {
        $this->dictionary->flush();
    }

    public function deleted(): void
    {
        $this->dictionary->flush();
    }
}
