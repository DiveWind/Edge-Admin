<?php

namespace App\Core\Monitor\Listeners;

use App\Core\Monitor\Actions\LogLogin;
use App\Core\Monitor\Enums\{LoginEvent, LoginFailureReason};
use App\Core\System\Models\User;
use Illuminate\Auth\Events\{Failed, Lockout, Login, Logout, PasswordReset};
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Fortify;
use Throwable;

/**
 * 登录日志（设计文档 §7.3）：监听 Laravel 与 Fortify 的标准认证事件，调用 LogLogin 写入 activitylog 的 `login` 日志。
 * 在 MonitorServiceProvider 中以订阅者登记（Event::subscribe）。
 *
 * | 事件 | event | reason | 账号 |
 * |---|---|---|---|
 * | Login | login | — | 登录的用户（via_remember：是否由“记住我”的 Cookie 恢复） |
 * | Logout | logout | — | 登出的用户 |
 * | Failed | failed | 账号已停用为 disabled，其余为 invalid_credentials | 事件带的用户，没有时按提交的用户名查找 |
 * | Fortify 的 TwoFactorAuthenticationFailed | failed | two_factor | 待验证的用户 |
 * | Lockout | lockout | — | 按提交的用户名查找 |
 * | PasswordReset | password_reset | — | 被重置密码的用户 |
 *
 * - 只记录后台用户（User）的事件；Failed 的 credentials 中含提交的密码，**只取其中的用户名**，密码不会传给 LogLogin；
 * - IP、User-Agent 取自当前请求（Lockout 取事件带的请求）；同步执行，不进队列（队列中拿不到请求）；
 * - 写入失败只 report()，不影响登录、登出本身。
 */
class RecordLoginActivity
{
    public function __construct(
        private readonly LogLogin $logLogin,
        private readonly Request $request,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * 登记监听的事件。
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            TwoFactorAuthenticationFailed::class => 'handleTwoFactorFailed',
            Lockout::class => 'handleLockout',
            PasswordReset::class => 'handlePasswordReset',
        ];
    }

    /**
     * 登录成功。Login 事件的 remember 只表示“记住我”被使用（勾选后登录、用 Cookie 恢复登录都为 true），
     * 区分两者要看守卫的 viaRemember()：SessionGuard 用 recaller Cookie 恢复登录时先置位再派发 Login。
     * 守卫按事件中的名称从 AuthManager 取（同一个已解析的实例）；没有 viaRemember() 的守卫（如 token）为 false。
     */
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $guard = $this->auth->guard($event->guard);
        $viaRemember = method_exists($guard, 'viaRemember') && $guard->viaRemember() === true;

        $this->record(LoginEvent::Login, $event->user, viaRemember: $viaRemember);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->record(LoginEvent::Logout, $event->user);
        }
    }

    /**
     * 登录失败：用户名或密码错误（Fortify），或停用账号用正确的密码登录（AuthenticateUser）。
     * 事件中的 credentials 只读取用户名。
     */
    public function handleFailed(Failed $event): void
    {
        $username = $event->credentials[Fortify::username()] ?? null;
        $username = is_string($username) ? $username : null;
        $account = $event->user instanceof User ? $event->user : $this->findAccount($username);
        $reason = $event->user instanceof User && ! $event->user->isEnabled()
            ? LoginFailureReason::Disabled
            : LoginFailureReason::InvalidCredentials;

        $this->record(LoginEvent::Failed, $account, $username, $reason);
    }

    /**
     * 两步验证的动态码或恢复码错误。
     */
    public function handleTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        if ($event->user instanceof User) {
            $this->record(LoginEvent::Failed, $event->user, reason: LoginFailureReason::TwoFactor);
        }
    }

    /**
     * 登录过于频繁被限流（SystemServiceProvider 的登录限流器在拒绝时派发）。
     */
    public function handleLockout(Lockout $event): void
    {
        $username = $event->request->input(Fortify::username());
        $username = is_string($username) ? $username : null;

        $this->record(LoginEvent::Lockout, $this->findAccount($username), $username, request: $event->request);
    }

    /**
     * 通过找回密码重置了密码（Fortify 的 POST /reset-password）。事件只带用户，不含新密码与重置 token。
     * 管理员在用户管理中重置密码不派发它（记在操作日志中）。
     */
    public function handlePasswordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            $this->record(LoginEvent::PasswordReset, $event->user);
        }
    }

    /**
     * 按提交的用户名查找账号（用户名保存为小写）。
     */
    private function findAccount(?string $username): ?User
    {
        if ($username === null || trim($username) === '') {
            return null;
        }

        return User::query()->where('username', mb_strtolower(trim($username)))->first();
    }

    private function record(
        LoginEvent $event,
        ?User $account,
        ?string $username = null,
        ?LoginFailureReason $reason = null,
        ?Request $request = null,
        bool $viaRemember = false,
    ): void {
        $request ??= $this->request;

        try {
            $this->logLogin->handle($event, $account, $username, $reason, $request->ip(), $request->userAgent(), $viaRemember);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
