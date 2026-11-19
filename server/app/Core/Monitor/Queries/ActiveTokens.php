<?php

namespace App\Core\Monitor\Queries;

use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 在线会话页的 token 列表（GET /monitor/online/tokens，设计文档 §6.6）：$actor 可见的、仍然有效的后台用户 token。
 *
 * - 有效：expires_at 为空或晚于现在，且配置了 sanctum.expiration（分钟）时创建于该时间之内——与 Sanctum 的 Guard 校验一致；
 * - 只含属于后台用户（多态别名 `user`）的 token，数据范围委托给所属用户（User::visibleTo()，§8.4）；
 * - 预加载 tokenable（所属用户），输出时不再查库。
 *
 * builder() 返回查询构造器，由调用方加筛选、排序与分页（query-builder）；isCurrent() 判断是否为本次请求使用的 token。
 */
class ActiveTokens
{
    /**
     * Execute the query.
     *
     * @return Builder<PersonalAccessToken>
     */
    public function builder(User $actor): Builder
    {
        $expiration = config('sanctum.expiration');
        $minutes = is_numeric($expiration) ? (int) $expiration : null;

        return PersonalAccessToken::query()
            ->whereHasMorph('tokenable', [User::class], fn (Builder $users) => $users->visibleTo($actor))
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($minutes !== null, fn (Builder $query) => $query->where('created_at', '>', now()->subMinutes((int) $minutes)))
            ->with('tokenable');
    }

    /**
     * $token 是否为 $actor 本次请求使用的 token。currentAccessToken() 的 PHPDoc 声明为 PersonalAccessToken，
     * 但会话 Cookie 认证时实际是没有 id 的 TransientToken，这里按 id 比较，两种情况都安全。
     */
    public function isCurrent(User $actor, PersonalAccessToken $token): bool
    {
        $currentId = data_get($actor->currentAccessToken(), 'id');

        return $currentId !== null && $currentId === $token->getKey();
    }
}
