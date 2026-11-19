<?php

namespace App\Http\Admin\Monitor\Resources;

use App\Core\Monitor\Models\Session;
use App\Core\System\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 在线会话的输出格式（Admin 入口，设计文档 §6.6）。
 *
 * - id 是会话 ID 的前 16 位（Session::publicId()）：完整的会话 ID 是登录凭据，不对外输出；不输出会话内容（payload）；
 * - user：`{ id, username, name }`（需预加载 user）；is_current：是否为发起本次请求的会话；
 * - last_activity 为带时区偏移的 ISO 8601。
 *
 * @mixin Session
 */
class OnlineSessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->publicId(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user instanceof User ? $this->user->only(['id', 'username', 'name']) : null),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'last_activity' => $this->lastActivityAt()->toIso8601String(),
            'is_current' => $this->isCurrent(),
        ];
    }
}
