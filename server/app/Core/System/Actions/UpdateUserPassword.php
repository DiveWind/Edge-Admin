<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\{DB, Session, Validator};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use SensitiveParameter;

/**
 * 修改密码，并让该用户的其他会话与全部 token 失效（设计文档 §7.2、§7.4）。
 *
 * Fortify 的“修改密码”（PUT /user/password）调用 update()：校验当前密码与新密码，保留发起修改的当前会话。
 * 其他会话的失效方式见 RevokeUserSessions：database 驱动直接删除；其他驱动由 Sanctum 的 AuthenticateSession
 * 比对会话中的密码哈希，在下一次请求时注销。密码本身已改变，因此不再需要 Auth::logoutOtherDevices()。
 */
class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(private readonly RevokeUserSessions $revokeSessions) {}

    /**
     * Fortify：校验输入后修改当前用户的密码。
     *
     * @param  array<array-key, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $validated = Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validate();

        $this->handle($user, Arr::string($validated, 'password'), Session::getId());
    }

    /**
     * Execute the action.
     *
     * @param  string|null  $exceptSessionId  保留的会话（发起修改的当前会话）
     */
    public function handle(User $user, #[SensitiveParameter] string $password, ?string $exceptSessionId = null): void
    {
        DB::transaction(function () use ($user, $password, $exceptSessionId): void {
            $user->forceFill(['password' => $password])->save();

            $this->revokeSessions->handle($user, $exceptSessionId);
        });
    }
}
