<?php

namespace App\Http\Admin\Message\Resources;

use App\Core\Message\Models\Announcement;
use App\Core\System\Models\Role;
use App\Http\Admin\System\Resources\AttachmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * 公告管理的输出格式（Admin 入口，设计文档 §6.9）。
 *
 * - roles：目标角色 `[{ name, title }]`（target 为 all 时为 `[]`）；publisher：发布人 `{ id, username, name }`（未发布或已删除时为 null）；
 * - content（净化后的 HTML）与 attachments（附件，签名地址由本接口签发，格式同 AttachmentResource）只在 withDetails() 时输出
 *   （详情与新增、修改、发布、撤回的响应），列表不含；
 * - 时间为带时区偏移的 ISO 8601。
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
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
            'status' => $this->status,
            'target' => $this->target,
            'roles' => $this->roles->map(fn (Role $role): array => ['name' => $role->name, 'title' => $role->title])->values()->all(),
            'attachments' => $this->details ? AttachmentResource::collection($this->files) : new MissingValue,
            'published_at' => $this->published_at?->toIso8601String(),
            'publisher' => $this->publisher?->only(['id', 'username', 'name']),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * 输出前需要预加载的关联：列表为 roles、publisher，详情另加附件 files。
     *
     * @return list<string>
     */
    public static function relations(bool $details = false): array
    {
        return $details ? ['roles', 'publisher', 'files'] : ['roles', 'publisher'];
    }
}
