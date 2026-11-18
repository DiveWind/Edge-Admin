<?php

namespace App\Core\System\Models;

use App\Core\System\Enums\UserStatus;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\UserFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Hidden};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 后台用户（多态别名 `user`，见 AppServiceProvider）。
 *
 * - 用户名登录（Fortify 的 username 字段，保存为小写）；邮箱可空，用于找回密码；
 * - 状态见 UserStatus：停用的账号不能登录，已登录的会话与 token 立即失效（设计文档 §7.3）；
 * - 两步验证（Fortify）：密钥与恢复码加密保存，不对外输出；
 * - HasApiTokens：Sanctum 的 token（设备、App 等受限入口），后台使用会话 Cookie。
 */
#[Fillable(['username', 'name', 'email', 'avatar', 'password', 'status'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable, UsesAppTimezone;

    /**
     * 账号是否启用。
     */
    public function isEnabled(): bool
    {
        return $this->status === UserStatus::Enabled;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
