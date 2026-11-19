<?php

namespace App\Core\Message\Notifications;

use App\Core\Message\Data\NotificationContentData;
use App\Core\Message\Enums\NotificationLevel;
use App\Core\System\Enums\ExportStatus;
use App\Core\System\Models\Export;
use App\Core\System\Services\ExporterRegistry;

/**
 * 导出结束的站内通知（类型 `message.export`，设计文档 §6.10）：System 的导出完成或失败时，由 NotifyExportFinished 发给发起人。
 *
 * - title：前端 i18n key `message.notification.exportCompleted` / `message.notification.exportFailed`；
 * - params：`{ title: 导出的显示名（按发起时的语言翻译）, rows: 写出的行数（失败时为 null） }`；
 * - level：完成 success、失败 error；
 * - link：前端的“我的导出”路由（config('core.exports.link')，`{id}` 替换为导出 id）。**不放下载地址**：签名地址会过期，通知会保留很久；
 * - subject：导出记录。
 */
class ExportFinished extends InboxNotification
{
    public function __construct(public readonly Export $export) {}

    /**
     * 对外的类型名。
     */
    public static function type(): string
    {
        return 'message.export';
    }

    /**
     * 通知的内容。
     */
    public function toInbox(object $notifiable): NotificationContentData
    {
        $completed = $this->export->status === ExportStatus::Completed;

        return new NotificationContentData(
            title: $completed ? 'message.notification.exportCompleted' : 'message.notification.exportFailed',
            level: $completed ? NotificationLevel::Success : NotificationLevel::Error,
            link: str_replace('{id}', (string) $this->export->id, config()->string('core.exports.link')),
            params: ['title' => $this->title(), 'rows' => $completed ? $this->export->row_count : null],
            subject: $this->export,
        );
    }

    /**
     * 导出的显示名（按发起时的语言翻译；导出类已不再登记时为对外名称）。
     */
    private function title(): string
    {
        $exporters = resolve(ExporterRegistry::class);

        if (! $exporters->has($this->export->exporter)) {
            return $this->export->exporter;
        }

        $title = $exporters->get($this->export->exporter)->title();
        $translated = __($title, [], $this->export->locale);

        return is_string($translated) ? $translated : $title;
    }
}
