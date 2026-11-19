<?php

namespace App\Core\Message\Models;

use App\Core\System\Models\User;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Carbon\CarbonInterface;
use Database\Factories\Core\Message\Models\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\{Builder, MassPrunable, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知中心的一条通知（多态别名 `notification`，见 MessageServiceProvider；设计文档 §6.9）。
 *
 * - 继承框架的 DatabaseNotification（同一张官方 notifications 表，id 为 UUID，data 为 array，read_at 为日期）；
 *   通知由框架的 database 通道写入（$user->notify()，写入 User::notifications()），本模型用于读取、授权与清理；
 * - type：通知类的对外类型名（InboxNotification::type()），不是 PHP 类名；
 * - data：NotificationContentData 的内容（title、content、level、link、params，以及只供后端使用的 subject）；
 * - notifiable：接收人（多态别名 `user`）；ownedBy($user) / isOwnedBy($user)：是否属于某人（接口只让本人访问）；
 * - about($record)：关于某条记录的通知（data->subject），如撤回公告时删除已发出的通知；
 * - 已读、创建超过 config('core.message.notifications.read_ttl') 天的通知由 `model:prune` 删除（MassPrunable，未读的不清理）。
 *
 * 写入（标记已读、删除）只在 Message 模块的 Action 中进行（设计文档 §5.2）。
 *
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property array<string, mixed> $data
 * @property CarbonInterface|null $read_at
 * @property CarbonInterface|null $created_at
 */
class Notification extends DatabaseNotification
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory, MassPrunable, UsesAppTimezone;

    /**
     * 是否属于 $user（接收人是该用户）。
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->notifiable_type === $user->getMorphClass() && $this->notifiable_id === $user->getKey();
    }

    /**
     * 是否已读。
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * data 中的一个字符串值（不存在或不是字符串时为 null）。
     */
    public function dataString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * 翻译参数（data.params；不是对象时为空）。
     *
     * @return array<string, mixed>
     */
    public function params(): array
    {
        $params = $this->data['params'] ?? null;

        return is_array($params) ? array_filter($params, is_string(...), ARRAY_FILTER_USE_KEY) : [];
    }

    /**
     * 只保留 $user 的通知。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->getKey());
    }

    /**
     * 只保留关于 $subject 的通知（data->subject 的多态别名与主键，JSON 路径查询，没有索引）。
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function about(Builder $query, Model $subject): void
    {
        $query->where('data->subject->type', $subject->getMorphClass())->where('data->subject->id', $subject->getKey());
    }

    /**
     * 清理（model:prune）：已读、创建于 read_ttl 天之前的通知。
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNotNull('read_at')
            ->where('created_at', '<=', now()->subDays(config()->integer('core.message.notifications.read_ttl')));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifiable_id' => 'integer',
        ];
    }
}
