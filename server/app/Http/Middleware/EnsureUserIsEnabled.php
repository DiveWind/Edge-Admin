<?php

namespace App\Http\Middleware;

use App\Core\System\Actions\RevokeUserSessions;
use App\Core\System\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 停用账号立即生效（设计文档 §7.3）：已认证的请求如果来自停用的账号，删除该用户的全部会话与 token，
 * 注销当前会话并返回 401（`This account has been disabled.`）。未认证的请求直接放行，由路由的 auth 中间件决定。
 *
 * 放在入口中间件组中（会话中间件之后），对组内全部路由生效，包括 Fortify 的路由；
 * 参数为检查的守卫，默认 sanctum（会话 Cookie 与 Bearer token 都能识别），如 `EnsureUserIsEnabled::class.':sanctum'`。
 * 停用操作（DisableUser）已删除 database 驱动的会话，这里兜底其他会话驱动与绕过 Action 的状态修改。
 */
class EnsureUserIsEnabled
{
    public function __construct(private readonly RevokeUserSessions $revokeSessions) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = $guards === [] ? ['sanctum'] : $guards;

        foreach ($guards as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user instanceof User && ! $user->isEnabled()) {
                $this->revokeSessions->handle($user);
                $this->logoutCurrentSession($request);

                throw new AuthenticationException(__('This account has been disabled.'), $guards);
            }
        }

        return $next($request);
    }

    /**
     * 注销当前会话（Sanctum 有状态请求使用的会话守卫）。
     */
    protected function logoutCurrentSession(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        foreach (Arr::wrap(config('sanctum.guard', 'web')) as $guard) {
            $sessionGuard = is_string($guard) ? Auth::guard($guard) : null;

            if ($sessionGuard instanceof StatefulGuard) {
                $sessionGuard->logout();
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
