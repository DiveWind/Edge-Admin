<?php

namespace App\Models;

use App\Collections\TreeCollection;
use App\Enums\{PermissionType, Status};
use App\Models\Concerns\StoresDatesInAppTimezone;
use App\Observers\PermissionObserver;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\{CollectedBy, Fillable, ObservedBy, Scope};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

/**
 * 菜单权限树节点：目录（directory）/ 菜单（menu）/ 操作（action），code 即权限码。
 *
 * - 内置节点（is_system）由 config/permissions.php 声明、`permission:sync` 同步；自定义节点在菜单管理界面维护。
 * - 停用（status = disabled）的节点及其全部后代不生效：权限判定与菜单都会排除（见 PermissionRegistry）。
 * - 隐藏（is_visible = false）只影响菜单显示，权限仍然有效。
 */
#[CollectedBy(TreeCollection::class)]
#[ObservedBy(PermissionObserver::class)]
#[Fillable(['parent_id', 'name', 'code', 'type', 'path', 'icon', 'is_external', 'is_visible', 'status', 'sort', 'remark'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory, StoresDatesInAppTimezone;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_external' => false,
        'is_visible' => true,
        'status' => 'active',
        'is_system' => false,
        'sort' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'type' => PermissionType::class,
            'is_external' => 'boolean',
            'is_visible' => 'boolean',
            'status' => Status::class,
            'is_system' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Permission, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'parent_id');
    }

    /**
     * @return HasMany<Permission, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Permission::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * 指定节点及其全部后代的 id（在 PHP 侧遍历，避免方言 SQL）。
     *
     * @return list<int>
     */
    public static function descendantAndSelfIds(int $id): array
    {
        $childrenByParent = static::query()
            ->get(['id', 'parent_id'])
            ->groupBy(fn (Permission $permission): int => (int) $permission->parent_id);

        $ids = [];
        $pending = [$id];

        while ($pending !== []) {
            $current = array_shift($pending);

            if (in_array($current, $ids, true)) {
                continue;
            }

            $ids[] = $current;

            foreach ($childrenByParent->get($current, []) as $child) {
                $pending[] = $child->id;
            }
        }

        return $ids;
    }

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
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
            ->when($filters['type'] ?? null, fn (Builder $q, string $value) => $q->where('type', $value))
            ->when($filters['status'] ?? null, fn (Builder $q, string $value) => $q->where('status', $value));
    }
}
