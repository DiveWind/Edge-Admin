<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum 访问令牌（Sanctum::usePersonalAccessTokenModel 注册），额外记录签发时的 ip / user_agent，
 * 一个未过期的 token 即一个"在线会话"。
 *
 * @property string|null $ip
 * @property string|null $user_agent
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * 令牌所属用户（tokenable 为 User 时）。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tokenable_id')->withTrashed();
    }

    /**
     * 属于用户且未过期的令牌。
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function online(Builder $query): void
    {
        $query
            ->where('tokenable_type', (new User)->getMorphClass())
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['username'] ?? null, fn (Builder $q, string $value) => $q->whereHas(
                'user',
                fn (Builder $user) => $user->whereLike('username', "%{$value}%"),
            ))
            ->when($filters['ip'] ?? null, fn (Builder $q, string $value) => $q->whereLike('ip', "%{$value}%"));
    }
}
