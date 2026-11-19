<?php

namespace App\Http\Admin\Message\Resources;

use App\Core\Message\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 我的通知的输出格式（Admin 入口，设计文档 §6.9）。
 *
 * - type：通知的对外类型名（InboxNotification::type()），不是 PHP 类名；
 * - title、content、level、link、params：通知的内容（NotificationContentData），title / content 是前端的 i18n key 或纯文本，
 *   params 是翻译参数（为空时为 `[]`）；不输出只供后端使用的 subject；
 * - is_read、read_at；时间为带时区偏移的 ISO 8601。
 *
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->dataString('title') ?? '',
            'content' => $this->dataString('content'),
            'level' => $this->dataString('level') ?? 'info',
            'link' => $this->dataString('link'),
            'params' => $this->params(),
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
