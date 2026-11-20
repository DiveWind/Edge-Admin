<?php

namespace App\Http\Admin\Monitor\Resources;

use App\Core\Monitor\Services\CurrentAccessToken;
use App\Core\System\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 在线会话页中 token 的输出格式（Admin 入口，设计文档 §6.6）。
 *
 * - user：`{ id, username, name }`（需预加载 tokenable）；is_current：是否为本次请求使用的 token；
 * - 不输出 token 本身（库中只有哈希）；时间为带时区偏移的 ISO 8601。
 *
 * @mixin PersonalAccessToken
 */
class OnlineTokenResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $actor = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'abilities' => $this->abilities,
            'user' => $this->whenLoaded('tokenable', fn (): ?array => $this->tokenable instanceof User ? $this->tokenable->only(['id', 'username', 'name']) : null),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'is_current' => $actor instanceof User && $this->resource instanceof PersonalAccessToken && resolve(CurrentAccessToken::class)->is($actor, $this->resource),
        ];
    }
}
