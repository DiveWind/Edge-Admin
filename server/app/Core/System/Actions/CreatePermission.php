<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Permission, User};
use App\Support\Database\UniqueConstraint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 新增菜单与权限节点（POST /system/menus，设计文档 §6.5、§8.2、§8.6）：自定义节点（is_builtin = false），guard 为 web。
 *
 * - 可写入的字段为 Permission 的 `#[Fillable]`（guard_name 除外），其余键忽略：meta.title / meta.icon / meta.order 已由入口转换为
 *   title / icon / sort 列，meta 只含 Permission::META_KEYS 中的键；
 * - 类型层级：catalog / menu / embedded / link 只能挂在根或 catalog 下，button 只能挂在 menu 下 → 422（parent_id）；
 * - 权限码（name）全局唯一、非按钮节点的 path 唯一：表单已校验，并发冲突时同样返回 422（name / path，UniqueConstraint）；
 * - 各类型需要与不允许的字段（path、component、meta.iframeSrc、meta.link）由表单按类型校验；
 * - 未提交的字段取列默认值：parent_id 为 null（根），status 为 enabled。
 *
 * 保存后 spatie 自动清除权限缓存（Permission 的 saved 事件），`/menu/all`、`/auth/codes` 随即按新数据计算。
 */
class CreatePermission
{
    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段
     *
     * @throws ValidationException
     */
    public function handle(array $attributes): Permission
    {
        return DB::transaction(function () use ($attributes): Permission {
            // 显式指定 guard：spatie 默认取当前请求的 guard（auth:sanctum 下为 sanctum）
            $permission = new Permission(['guard_name' => User::GUARD]);
            $permission->fill(array_intersect_key($attributes, array_flip(array_diff($permission->getFillable(), ['guard_name']))));
            $permission->is_builtin = false;

            // 共享锁：与 DeletePermission 的行锁互斥，上级在检查后被删除时不会撞上外键（500）
            $parent = $permission->parent_id === null ? null : Permission::query()->sharedLock()->find($permission->parent_id, ['id', 'type']);

            if ($permission->parent_id !== null && $parent === null) {
                throw ValidationException::withMessages(['parent_id' => [__('validation.exists', ['attribute' => __('Parent node')])]]);
            }

            if (! $permission->type->canBeChildOf($parent?->type)) {
                throw ValidationException::withMessages(['parent_id' => [$permission->type->hierarchyMessage()]]);
            }

            UniqueConstraint::save($permission, ['name' => __('Permission code'), 'path' => __('Path')]);

            return $permission->refresh();
        });
    }
}
