<?php

namespace App\Core\System\Events;

use App\Core\System\Models\Export;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * 导出结束（完成或失败，设计文档 §6.10）：GenerateExport、FailExport 在状态写入（事务提交）后派发。
 * Message 监听它给发起人发送通知中心的通知（System 不依赖 Message）；状态见 $export->status。
 */
class ExportFinished
{
    use Dispatchable;

    public function __construct(public readonly Export $export) {}
}
