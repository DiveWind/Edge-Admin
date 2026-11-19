<?php

namespace App\Core\System\Observers;

use App\Core\System\Services\Dictionary;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * 字典类型、字典项写入后清除字典缓存（设计文档 §6.7）：在事务提交之后执行，
 * 避免其他请求在提交前把旧数据重新写回缓存；没有事务时立即执行。
 */
class DictionaryObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly Dictionary $dictionary) {}

    /**
     * 新增或修改之后。
     */
    public function saved(): void
    {
        $this->dictionary->flush();
    }

    /**
     * 删除之后（删除类型时其字典项一并删除，同样清除）。
     */
    public function deleted(): void
    {
        $this->dictionary->flush();
    }
}
