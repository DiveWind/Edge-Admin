<?php

namespace App\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Core\System\Models\Concerns\{HasAttachments, HasDataScope};
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\UserFactory;
use Illuminate\Database\Eloquent\Attributes\{Fillable, Hidden};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphOne};
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
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
 * - created_by：创建人，供“仅本人”数据范围使用（§8.4、§8.5），由 Action 写入；
 * - HasDataScope：数据权限，`User::query()->visibleTo($actor)` 与 `$user->isVisibleTo($actor)`（用户管理的列表、详情、Policy 使用）；
 *   “仅本人”范围下，自己创建的用户与本人都可见（dataScopeOwnerColumns()）。可见不等于可操作：删除、停用自己仍由 UserPolicy 拒绝；
 * - HasAttachments：头像是 `avatar` 用途上绑定的附件（avatar()，设计文档 §6.8），由本人在个人资料中设置；用户删除时一并删除。
 *
 * `#[Fillable]` 即新增、修改用户时可由输入写入的字段（Action 只取输入中的这些键）；
 * 密码、created_by 只由 Action 显式 forceFill（新增、重置密码、修改密码）。
 */
#[Fillable(['username', 'name', 'email', 'status'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasAttachments, HasDataScope, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable, UsesAppTimezone;

    /**
     * spatie 的 guard：角色与权限统一使用 web（用设备 token 认证的也是同一个 User，设计文档 §8.1）。
     */
    public const string GUARD = 'web';

    /**
     * 头像附件的用途名（设计文档 §6.8）。
     */
    public const string AVATAR_COLLECTION = 'avatar';

    /**
     * spatie 查找、分配角色时使用的 guard。不声明时 spatie 取当前请求的默认 guard（auth:sanctum 下为 sanctum），会找不到角色。
     */
    public function guardName(): string
    {
        return self::GUARD;
    }

    /**
     * 用户名的规范形式：统一存为小写（与 Fortify 登录时的 lowercase_usernames 一致，设计文档 §7.2）。
     */
    public static function normalizeUsername(string $username): string
    {
        return Str::lower($username);
    }

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
     * “仅本人”数据范围的归属列（§8.4）：自己创建的用户（created_by），以及本人（id）。
     *
     * @return non-empty-list<string>
     */
    public function dataScopeOwnerColumns(): array
    {
        return ['created_by', 'id'];
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
     * 头像（`avatar` 用途上绑定的附件，没有时为 null）。
     *
     * @return MorphOne<Attachment, $this>
     */
    public function avatar(): MorphOne
    {
        return $this->attachmentIn(self::AVATAR_COLLECTION);
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
