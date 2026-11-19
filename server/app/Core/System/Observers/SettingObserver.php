<?php

namespace App\Core\System\Observers;

use App\Core\System\Services\Settings;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * 系统参数写入后清除参数缓存（设计文档 §6.7）：在事务提交之后执行，
 * 避免其他请求在提交前把旧值重新写回缓存；没有事务时立即执行。
 */
class SettingObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * 新增或修改之后。
     */
    public function saved(): void
    {
        $this->settings->flush();
    }

    /**
     * 删除之后。
     */
    public function deleted(): void
    {
        $this->settings->flush();
    }
}
