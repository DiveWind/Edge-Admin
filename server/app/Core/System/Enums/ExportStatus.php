<?php

namespace App\Core\System\Enums;

use App\Core\System\Contracts\{HasColor, HasLabel};

/**
 * 导出任务的状态（设计文档 §6.10）：queued（排队中）→ running（进行中）→ completed（已完成）或 failed（失败）。
 *
 * 排队中与进行中的导出计入同一用户的并发上限（config('core.exports.max_active')）。
 * 实现 HasLabel、HasColor：显示名称与语义色由后端统一下发给前端（设计文档 §5.5、§10.1）。
 */
enum ExportStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * 排队中与进行中（计入并发上限）的状态。
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Queued, self::Running];
    }

    /**
     * 显示名称（多语言）。
     */
    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Running => __('Running'),
            self::Completed => __('Completed'),
            self::Failed => __('Failed'),
        };
    }

    /**
     * 语义色（antd Tag）。
     */
    public function color(): string
    {
        return match ($this) {
            self::Queued => 'default',
            self::Running => 'processing',
            self::Completed => 'success',
            self::Failed => 'error',
        };
    }
}
