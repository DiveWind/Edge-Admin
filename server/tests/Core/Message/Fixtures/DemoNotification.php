<?php

namespace Tests\Core\Message\Fixtures;

use App\Core\Message\Data\NotificationContentData;
use App\Core\Message\Enums\NotificationLevel;
use App\Core\Message\Notifications\InboxNotification;
use Illuminate\Database\Eloquent\Model;

/**
 * 测试用的模块通知（类型 `demo.example`）：按构造参数生成内容。
 */
class DemoNotification extends InboxNotification
{
    /**
     * @param  array<string, scalar|null>  $params
     * @param  list<string>  $channels
     */
    public function __construct(
        private readonly string $title = 'demo.notification.title',
        private readonly ?string $link = '/demo/1',
        private readonly array $params = ['name' => 'Alice'],
        private readonly ?Model $subject = null,
        private readonly array $channels = ['database'],
    ) {}

    public static function type(): string
    {
        return 'demo.example';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toInbox(object $notifiable): NotificationContentData
    {
        return new NotificationContentData(
            title: $this->title,
            content: 'demo.notification.content',
            level: NotificationLevel::Warning,
            link: $this->link,
            params: $this->params,
            subject: $this->subject,
        );
    }
}
