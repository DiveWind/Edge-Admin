<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\User;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\{Rule, ValidationException};
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Features;

/**
 * 修改个人资料：姓名、邮箱、头像（用户名与状态由管理员维护，这里不能修改；设计文档 §7.2）。
 *
 * Fortify 的“修改个人资料”（PUT /user/profile-information）调用 update()。邮箱变更后清除验证状态，
 * 开启邮箱验证（CORE_EMAIL_VERIFICATION）时向新邮箱发送验证邮件。
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Fortify：校验输入后修改当前用户的资料。未提交的 email、avatar 保持不变。
     *
     * @param  array<array-key, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        /** @var array{name: string, email?: string|null, avatar?: string|null} $validated */
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->getKey())],
            'avatar' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
        ])->validate();

        $this->handle($user, $validated);
    }

    /**
     * Execute the action.
     *
     * @param  array{name?: string, email?: string|null, avatar?: string|null}  $attributes
     */
    public function handle(User $user, array $attributes): void
    {
        $emailChanged = DB::transaction(function () use ($user, $attributes): bool {
            $user->fill($attributes);

            $emailChanged = $user->isDirty('email');

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            return $emailChanged;
        });

        if ($emailChanged && $user->email !== null && Features::enabled(Features::emailVerification())) {
            $user->sendEmailVerificationNotification();
        }
    }
}
