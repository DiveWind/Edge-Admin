<?php

namespace App\Models;

use App\Enums\DataScope;
use App\Enums\Status;
use App\Models\Concerns\StoresDatesInAppTimezone;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'code', 'data_scope', 'sort', 'status', 'remark'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, StoresDatesInAppTimezone;

    /**
     * 超级管理员角色编码：拥有全部权限，不可删除 / 禁用 / 改码。
     */
    public const string SUPER_ADMIN = 'super-admin';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data_scope' => DataScope::class,
            'sort' => 'integer',
            'status' => Status::class,
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * 自定义数据权限（data_scope = custom）可见的部门。
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class);
    }

    /**
     * 同步权限与自定义数据权限部门（仅 data_scope = custom 时保留部门）。
     *
     * @param  array<int, int|string>  $permissionIds
     * @param  array<int, int|string>  $departmentIds
     */
    public function syncAuthorizations(array $permissionIds, array $departmentIds): void
    {
        $this->permissions()->sync($permissionIds);
        $this->departments()->sync($this->data_scope === DataScope::Custom ? $departmentIds : []);
    }

    public function isSuperAdmin(): bool
    {
        return $this->code === self::SUPER_ADMIN;
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
            ->when($filters['name'] ?? null, fn (Builder $q, string $value) => $q->whereLike('name', "%{$value}%"))
            ->when($filters['code'] ?? null, fn (Builder $q, string $value) => $q->whereLike('code', "%{$value}%"))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value));
    }
}
