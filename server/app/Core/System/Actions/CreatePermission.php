<?php

namespace App\Core\System\Actions;

use App\Core\System\Enums\PermissionType;
use App\Core\System\Models\{Permission, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 新增菜单与权限节点（POST /system/menus，设计文档 §6.5、§8.2、§8.6）：自定义节点（is_builtin = false），guard 为 web。
 *
 * - 类型层级：catalog / menu / embedded / link 只能挂在根或 catalog 下，button 只能挂在 menu 下 → 422（parent_id）；
 * - 权限码（name）全局唯一、非按钮节点的 path 唯一：表单已校验，并发冲突时同样返回 422（name / path）；
 * - 各类型需要与不允许的字段（path、component、meta.iframeSrc、meta.link）由表单按类型校验；
 * - 未提交的字段取列默认值：parent_id 为 null（根），status 为 enabled。
 *
 * 保存后 spatie 自动清除权限缓存（Permission 的 saved 事件），`/menu/all`、`/auth/codes` 随即按新数据计算。
 */
class CreatePermission
{
    /**
     * 可写入的字段（其余键忽略）：meta.title / meta.icon / meta.order 已由入口转换为 title / icon / sort 列，
     * meta 只含 Permission::META_KEYS 中的键。
     *
     * @var list<string>
     */
    public const array ATTRIBUTES = ['parent_id', 'type', 'name', 'path', 'component', 'status', 'title', 'icon', 'sort', 'meta'];

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（ATTRIBUTES）
     *
     * @throws ValidationException
     */
    public function handle(array $attributes): Permission
    {
        return DB::transaction(function () use ($attributes): Permission {
            // 显式指定 guard：spatie 默认取当前请求的 guard（auth:sanctum 下为 sanctum）
            $permission = new Permission([...self::only($attributes), 'guard_name' => User::GUARD]);
            $permission->is_builtin = false;

            // 共享锁：与 DeletePermission 的行锁互斥，上级在检查后被删除时不会撞上外键（500）
            $parent = $permission->parent_id === null ? null : Permission::query()->sharedLock()->find($permission->parent_id, ['id', 'type']);

            if ($permission->parent_id !== null && $parent === null) {
                throw ValidationException::withMessages(['parent_id' => [__('validation.exists', ['attribute' => __('Parent node')])]]);
            }

            if (! $permission->type->canBeChildOf($parent?->type)) {
                throw ValidationException::withMessages(['parent_id' => [self::hierarchyMessage($permission->type)]]);
            }

            self::save($permission);

            return $permission->refresh();
        });
    }

    /**
     * 只保留可写入的字段。
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function only(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(self::ATTRIBUTES));
    }

    /**
     * 类型层级不合法时的提示（§8.2）。
     */
    public static function hierarchyMessage(PermissionType $type): string
    {
        return $type === PermissionType::Button
            ? __('Buttons can only be placed under a menu.')
            : __('Catalogs, menus, embedded pages and links can only be placed at the root or under a catalog.');
    }

    /**
     * 保存节点；权限码或 path 重复（并发写入时数据库的唯一索引）返回 422（name / path）。
     *
     * 保存包在嵌套事务（保存点）中：PostgreSQL 在语句失败后会中止整个事务，回滚到保存点后才能再查询是哪个字段重复。
     *
     * @throws ValidationException
     */
    public static function save(Permission $permission): void
    {
        try {
            DB::transaction(fn () => $permission->save());
        } catch (UniqueConstraintViolationException) {
            $nameTaken = Permission::query()
                ->where('name', $permission->name)
                ->when($permission->exists, fn (Builder $query) => $query->whereKeyNot($permission->getKey()))
                ->exists();

            throw ValidationException::withMessages($nameTaken
                ? ['name' => [__('validation.unique', ['attribute' => __('Permission code')])]]
                : ['path' => [__('validation.unique', ['attribute' => __('Path')])]]);
        }
    }
}
