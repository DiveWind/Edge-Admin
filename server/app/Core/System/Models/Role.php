<?php

namespace App\Core\System\Models;

use App\Core\System\Enums\Status;
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * 角色（多态别名 `role`，见 SystemServiceProvider），设计文档 §8.5：
 *
 * - name：角色标识（alpha_dash、唯一），`/user/info` 的 roles 输出启用角色的标识；超管角色为 SUPER_ADMIN；
 * - title：显示名；status：停用的角色，其权限与数据范围都不生效；
 * - data_scope：数据范围（§8.4，新建角色默认 self）；remark：备注；sort：排序。
 *
 * 写入只在 System 模块的 Action 与同步命令中进行（设计文档 §5.2）；分配权限用 syncPermissions()，
 * 判定一律走 Gate（PermissionRegistry），不使用 spatie 的判定 API（§8.1）。
 *
 * @property int $id
 */
#[Fillable(['name', 'guard_name', 'title', 'status', 'data_scope', 'remark', 'sort'])]
class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * 超管角色的标识：拥有全部启用节点的权限码（§8.1），不能删除、停用、改标识（§8.6）。
     */
    public const string SUPER_ADMIN = 'super-admin';

    /**
     * 角色是否启用。
     */
    public function isEnabled(): bool
    {
        return $this->status === Status::Enabled;
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
            'sort' => 'integer',
        ];
    }
}
