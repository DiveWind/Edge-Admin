<?php

namespace App\Core\System\Actions;

use App\Core\System\Models\{Role, User};
use App\Core\System\Services\Manageability;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 新增角色（POST /system/roles，设计文档 §6.5、§8.5）：保存字段，再按 SyncRolePermissions 的规则保存权限码。
 *
 * - 标识（name）唯一：表单已校验，并发冲突时同样返回 422（name）；
 * - 数据范围防提权：非超管提交的 data_scope 不在自己可授予的范围内 → 422（data_scope，§8.5）；
 * - 未提交的字段取列默认值：status 为 enabled，data_scope 为 self，sort 为 0。
 */
class CreateRole
{
    /**
     * 可写入的字段（其余键忽略）。
     *
     * @var list<string>
     */
    public const array ATTRIBUTES = ['name', 'title', 'status', 'data_scope', 'remark', 'sort'];

    public function __construct(private readonly SyncRolePermissions $syncPermissions) {}

    /**
     * Execute the action.
     *
     * @param  array<string, mixed>  $attributes  已校验的字段（ATTRIBUTES）
     * @param  list<string>  $permissions  权限码
     *
     * @throws ValidationException
     */
    public function handle(array $attributes, array $permissions, User $actor): Role
    {
        return DB::transaction(function () use ($attributes, $permissions, $actor): Role {
            // 显式指定 guard：spatie 默认取当前请求的 guard（auth:sanctum 下为 sanctum）
            $role = new Role([...self::only($attributes), 'guard_name' => User::GUARD]);

            self::ensureGrantableDataScope($role, $actor);
            self::save($role);

            $this->syncPermissions->handle($role, $permissions, $actor);

            return $role->refresh();
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
     * 数据范围防提权（§8.5）：提交（改动）了 data_scope 时，它必须在操作者可授予的范围内（Manageability::canGrantDataScope()，即 DataScopeRegistry::grantableKeysFor()），
     * 否则 → 422（data_scope）。未提交或保持原值时不检查，与权限码“管不到的保持不变”同一思路。
     *
     * @throws ValidationException
     */
    public static function ensureGrantableDataScope(Role $role, User $actor): void
    {
        if (! $role->isDirty('data_scope') || resolve(Manageability::class)->canGrantDataScope($actor, $role->data_scope)) {
            return;
        }

        throw ValidationException::withMessages([
            'data_scope' => [__('You cannot grant a data scope you do not have.')],
        ]);
    }

    /**
     * 保存角色；标识重复（并发写入时数据库的唯一索引）返回 422（name）。
     *
     * @throws ValidationException
     */
    public static function save(Role $role): void
    {
        try {
            $role->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => [__('validation.unique', ['attribute' => __('Identifier')])],
            ]);
        }
    }
}
