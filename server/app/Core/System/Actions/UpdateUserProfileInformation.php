<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Attachment, User};
use App\Core\System\Rules\OwnedAttachment;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\{Rule, ValidationException};
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Features;

/**
 * 修改个人资料：姓名、邮箱、头像（用户名与状态由管理员维护，这里不能修改；设计文档 §7.2、§6.8）。
 *
 * Fortify 的“修改个人资料”（PUT /user/profile-information）调用 update()。邮箱变更后清除验证状态，
 * 开启邮箱验证（CORE_EMAIL_VERIFICATION）时向新邮箱发送验证邮件。
 *
 * 头像（avatar_id）：附件 id——自己上传的、未绑定的图片（OwnedAttachment，MIME 限预览白名单），或当前的头像；null 去掉头像。
 * 绑定到 `avatar` 用途（SyncAttachments），被替换、去掉的原头像连同文件删除（事务提交后）。
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(private readonly SyncAttachments $syncAttachments) {}

    /**
     * Fortify：校验输入后修改当前用户的资料。未提交的 email、avatar_id 保持不变。
     *
     * @param  array<array-key, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        /** @var array{name: string, email?: string|null, avatar_id?: int|string|null} $validated */
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->getKey())],
            'avatar_id' => ['sometimes', 'nullable', 'integer', new OwnedAttachment($user, record: $user, collection: User::AVATAR_COLLECTION, mimeTypes: Attachment::PREVIEWABLE_MIME_TYPES)],
        ])->validate();

        $this->handle($user, $validated);
    }

    /**
     * Execute the action.
     *
     * @param  array{name?: string, email?: string|null, avatar_id?: int|string|null}  $attributes
     *
     * @throws ValidationException
     */
    public function handle(User $user, array $attributes): void
    {
        $emailChanged = DB::transaction(function () use ($user, $attributes): bool {
            $user->fill(array_intersect_key($attributes, array_flip(['name', 'email'])));

            $emailChanged = $user->isDirty('email');

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            if (array_key_exists('avatar_id', $attributes)) {
                $avatar = $attributes['avatar_id'];
                $this->syncAttachments->handle($user, User::AVATAR_COLLECTION, $avatar === null ? [] : [$avatar], 'avatar_id');
            }

            return $emailChanged;
        });

        if ($emailChanged && $user->email !== null && Features::enabled(Features::emailVerification())) {
            $user->sendEmailVerificationNotification();
        }
    }
}
