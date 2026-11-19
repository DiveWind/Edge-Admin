<?php

namespace App\Core\Message\Notifications;

use App\Core\Message\Data\NotificationContentData;
use App\Core\System\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * 通知中心的通知（设计文档 §5.5、§6.9）：模块的通知类都继承它（tests/Arch 强制），统一出现在通知中心。
 *
 * ```php
 * class ArticleApproved extends InboxNotification
 * {
 *     public function __construct(private readonly Article $article) {}
 *
 *     public static function type(): string
 *     {
 *         return 'content.article_approved';
 *     }
 *
 *     public function toInbox(object $notifiable): NotificationContentData
 *     {
 *         return new NotificationContentData(title: 'content.notification.articleApproved', params: ['title' => $this->article->title],
 *             level: NotificationLevel::Success, link: "/content/article/{$this->article->id}", subject: $this->article);
 *     }
 * }
 *
 * $user->notify(new ArticleApproved($article));
 * ```
 *
 * - type()：对外的类型名（小写点分，以模块名开头，全局唯一，tests/Arch 检查）。databaseType()、broadcastType() 返回它：
 *   库中的 type 列、接口与推送中的 type 都不出现 PHP 类名，移动、重命名类不用迁移数据；
 * - toInbox()：内容（NotificationContentData：title、content、level、link、params、subject），toDatabase() / toArray() 由它生成，结构统一；
 * - via()：默认只有 database，模块可以追加 mail 等通道；项目装了 Reverb 后加上 broadcast 即可实时推送（载荷为 toArray()）；
 * - 停用的用户不接收（shouldSend()，所有通道）；
 * - 需要排队发送时，子类实现 ShouldQueue（已使用 Queueable）；大量接收人时自己分批（如公告的 SendAnnouncement）。
 */
abstract class InboxNotification extends Notification
{
    use Queueable;

    /**
     * 类型名的格式：小写点分，至少两段（第一段是模块名）。
     */
    public const string TYPE_PATTERN = '/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/';

    /**
     * 对外的类型名：小写点分、以模块名开头、全局唯一，如 `message.announcement`、`content.article_approved`。
     */
    abstract public static function type(): string;

    /**
     * 通知的内容。
     */
    abstract public function toInbox(object $notifiable): NotificationContentData;

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * 停用的用户不接收任何通道的通知（框架 NotificationSender 在发送每个通道前调用）。
     */
    final public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $notifiable instanceof User || $notifiable->isEnabled();
    }

    /**
     * 存入 notifications.type 的类型名（框架 DatabaseChannel 调用）：对外的类型名，而不是类名。
     */
    final public function databaseType(object $notifiable): string
    {
        return static::type();
    }

    /**
     * 广播推送中的类型名（框架 BroadcastNotificationCreated 调用）：与 databaseType() 相同。
     */
    final public function broadcastType(): string
    {
        return static::type();
    }

    /**
     * 存入 notifications.data 的内容（含只供后端使用的 subject）。
     *
     * @return array<string, mixed>
     */
    final public function toDatabase(object $notifiable): array
    {
        return $this->toInbox($notifiable)->toDatabase();
    }

    /**
     * 对外的内容（广播推送的载荷，与接口输出的字段相同，不含 subject）。
     *
     * @return array<string, mixed>
     */
    final public function toArray(object $notifiable): array
    {
        return $this->toInbox($notifiable)->toPayload();
    }
}
