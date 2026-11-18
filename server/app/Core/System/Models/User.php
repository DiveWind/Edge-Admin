<?php

namespace App\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\UserFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Hidden};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * 后台用户（多态别名 `user`，见 AppServiceProvider）。
 *
 * - 用户名登录（Fortify 的 username 字段，保存为小写）；邮箱可空，用于找回密码；
 * - 状态见 Status：停用的账号不能登录，已登录的会话与 token 立即失效（设计文档 §7.3）；
 * - 两步验证（Fortify）：密钥与恢复码加密保存，不对外输出；
 * - HasApiTokens：Sanctum 的 token（设备、App 等受限入口），后台使用会话 Cookie；
 * - HasRoles：spatie 的角色关联与分配（Action 中 syncRoles()）。判定一律走 can() / Gate / Policy（设计文档 §8.1），
 *   不使用 hasRole() 等 spatie 的判定 API（PHPStan 拦截）；除“是否超管”（isSuperAdmin()）外不按角色判断；
 * - created_by：创建人，供“仅本人”数据范围使用（§8.4、§8.5），由 Action 写入。
 */
#[Fillable(['username', 'name', 'email', 'avatar', 'password', 'status'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable, UsesAppTimezone;

    /**
     * 账号是否启用。
     */
    public function isEnabled(): bool
    {
        return $this->status === Status::Enabled;
    }

    /**
     * 是否超管：拥有启用的 super-admin 角色（停用的角色不生效，§8.5）。
     */
    public function isSuperAdmin(): bool
    {
        return $this->enabledRoles()->contains(fn (Role $role): bool => $role->name === Role::SUPER_ADMIN);
    }

    /**
     * 启用的角色（停用的角色，其权限与数据范围都不生效）。读取已加载的 roles 关联，没有时查询一次。
     *
     * @return Collection<int, Role>
     */
    public function enabledRoles(): Collection
    {
        /** @var Collection<int, Role> $roles */
        $roles = $this->roles;

        return $roles->filter(fn (Role $role): bool => $role->isEnabled())->values();
    }

    /**
     * 创建人（“仅本人”数据范围按它判断）。
     *
     * @return BelongsTo<self, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'created_by' => 'integer',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
