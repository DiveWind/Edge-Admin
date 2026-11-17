<?php

namespace App\Http\Resources;

use App\Models\{PersonalAccessToken, User};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 在线会话（未过期的访问令牌）；is_current 表示是否为发起本次请求的会话。
 *
 * @mixin PersonalAccessToken
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
        $current = $request->user()?->currentAccessToken();

        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn (?User $user): ?array => $user?->only('id', 'name', 'username')),
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_current' => $current instanceof PersonalAccessToken && $current->is($this->resource),
        ];
    }
}
