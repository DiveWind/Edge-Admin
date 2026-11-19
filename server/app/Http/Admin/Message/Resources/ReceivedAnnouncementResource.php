<?php

namespace App\Http\Admin\Message\Resources;

use App\Core\Message\Models\Announcement;
use App\Http\Admin\System\Resources\AttachmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * 我收到的公告的输出格式（Admin 入口，设计文档 §6.9）：只输出接收人需要的字段，不输出状态、目标与创建人等管理字段。
 *
 * - publisher：发布人 `{ id, username, name }`（已删除时为 null）；
 * - content（净化后的 HTML）与 attachments（附件，签名地址由本接口签发：接收人通过它下载，不经过 /attachments/{id}）只在 withDetails() 时输出；
 * - 时间为带时区偏移的 ISO 8601。
 *
 * @mixin Announcement
 */
class ReceivedAnnouncementResource extends JsonResource
{
    /**
     * 是否输出 content 与 attachments。
     */
    private bool $details = false;

    /**
     * 输出 content 与 attachments（详情）。
     */
    public function withDetails(): static
    {
        $this->details = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->details ? $this->content : new MissingValue,
            'level' => $this->level,
            'attachments' => $this->details ? AttachmentResource::collection($this->files) : new MissingValue,
            'published_at' => $this->published_at?->toIso8601String(),
            'publisher' => $this->publisher?->only(['id', 'username', 'name']),
        ];
    }
}
