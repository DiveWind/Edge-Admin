<?php

namespace App\Models;

use App\Enums\DataScope;
use App\Enums\Status;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\StoresDatesInAppTimezone;
use App\Services\PermissionRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property-read Collection<int, Role> $roles
 */
#[Fillable(['username', 'name', 'email', 'phone', 'avatar', 'department_id', 'status', 'password'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasDataScope, HasFactory, Notifiable, SoftDeletes, StoresDatesInAppTimezone;

    /**
     * 受保护的初始超级管理员账号 id。
     */
    public const int ROOT_ID = 1;

    /**
     * 当前请求内缓存的权限码。
     *
     * @var array<int, string>|null
     */
    protected ?array $cachedPermissionCodes = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * @return BelongsToMany<Position, $this>
     */
    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class);
    }

    public function isRoot(): bool
    {
        return $this->getKey() === self::ROOT_ID;
    }

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->activeRoles()->contains(fn (Role $role): bool => $role->isSuperAdmin());
    }

    /**
     * 是否拥有权限码；超级管理员拥有全部权限，但停用节点（含祖先停用）的权限码对所有人都不生效。
     */
    public function hasPermission(string $code): bool
    {
        if ($this->isSuperAdmin()) {
            return ! app(PermissionRegistry::class)->isDisabledCode($code);
        }

        return in_array($code, $this->permissionCodes(), true);
    }

    /**
     * 用户实际拥有的权限码全集（超级管理员为全部权限码），请求内缓存。
     * 停用节点（含祖先停用）的权限码不生效，见 PermissionRegistry。
     *
     * @return array<int, string>
     */
    public function permissionCodes(): array
    {
        if ($this->cachedPermissionCodes !== null) {
            return $this->cachedPermissionCodes;
        }

        $registry = app(PermissionRegistry::class);

        if ($this->isSuperAdmin()) {
            return $this->cachedPermissionCodes = $registry->enabledCodes();
        }

        $this->loadMissing('roles.permissions');

        return $this->cachedPermissionCodes = $this->activeRoles()
            ->flatMap(fn (Role $role) => $role->permissions)
            ->filter(fn (Permission $permission): bool => $registry->isEnabled($permission->id))
            ->map(fn (Permission $permission): string => $permission->code)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 多角色时取范围最大的一个作为展示值；实际可见范围见 DataScopeResolver（并集）。
     */
    public function dataScope(): DataScope
    {
        if ($this->isSuperAdmin()) {
            return DataScope::All;
        }

        return $this->activeRoles()
            ->map(fn (Role $role): DataScope => $role->data_scope)
            ->sortByDesc(fn (DataScope $scope): int => $scope->weight())
            ->first() ?? DataScope::Self;
    }

    /**
     * 生效（启用）的角色。
     *
     * @return Collection<int, Role>
     */
    public function activeRoles(): Collection
    {
        return $this->roles->filter(fn (Role $role): bool => $role->status === Status::Active);
    }

    /**
     * 清除请求内的权限缓存（角色变更后调用）。
     */
    public function flushPermissionCache(): void
    {
        $this->cachedPermissionCodes = null;
        $this->unsetRelation('roles');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', Status::Active);
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['username'] ?? null, fn (Builder $q, string $value) => $q->whereLike('username', "%{$value}%"))
            ->when($filters['name'] ?? null, fn (Builder $q, string $value) => $q->whereLike('name', "%{$value}%"))
            ->when($filters['phone'] ?? null, fn (Builder $q, string $value) => $q->whereLike('phone', "%{$value}%"))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value))
            ->when($filters['department_id'] ?? null, fn (Builder $q, int|string $value) => $q->whereIn(
                'department_id',
                Department::descendantAndSelfIds((int) $value),
            ))
            ->when($filters['role_id'] ?? null, fn (Builder $q, int|string $value) => $q->whereRelation('roles', 'roles.id', $value));
    }

    /**
     * 用户自身没有 created_by，"本人数据" 即自己这条记录。
     *
     * @return array{department: string, owner: string}
     */
    public function dataScopeColumns(): array
    {
        return ['department' => 'department_id', 'owner' => 'id'];
    }
}
