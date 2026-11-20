<?php

namespace App\Core\System\Models;

use App\Core\System\Enums\{PermissionType, Status};
use App\Support\Eloquent\Concerns\UsesAppTimezone;
use Database\Factories\Core\System\Models\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * 菜单与权限节点（多态别名 `permission`，见 SystemServiceProvider）：菜单树直接建在 spatie 的 permissions 表上（设计文档 §8.2）。
 *
 * - name：权限码，同时是 Vben 的路由 name，全局唯一（小写点分：system、system.user、system.user.create）；
 * - type：节点类型（PermissionType），层级规则见 PermissionType::canBeChildOf()；
 * - title / icon / sort：输出为 meta.title / meta.icon / meta.order；meta 列只存白名单键（META_KEYS）；
 * - status：停用向子孙级联，停用的节点及其子孙对所有人（含超管）失效；
 * - is_builtin：内置节点（模块在 MenuRegistry 中声明的），由 core:sync-permissions 维护。
 *
 * 写入只在 System 模块的 Action 与同步命令中进行（设计文档 §5.2）；权限判定一律走 Gate（EffectivePermissions），
 * 不使用 spatie 的判定 API（§8.1）。
 *
 * @property int $id
 */
#[Fillable(['parent_id', 'type', 'name', 'guard_name', 'title', 'path', 'component', 'icon', 'sort', 'status', 'meta'])]
class Permission extends SpatiePermission
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory, UsesAppTimezone;

    /**
     * 权限码（name）的格式：小写点分，以字母开头，如 `system`、`system.user`、`system.user.create`。
     * 模块的声明（MenuRegistry）与菜单管理的写接口使用同一规则。
     */
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_-]*(\.[a-z0-9_-]+)*$/';

    /**
     * 生命线节点（§8.6）：菜单管理的查看与修改权限。它们及其祖先必须保持生效（不能停用、不能移到停用的节点下），
     * 否则包括超管在内的所有人都会被关在菜单管理之外，无法再启用。
     *
     * @var list<string>
     */
    public const array LIFELINE_NAMES = ['system.menu', 'system.menu.update'];

    /**
     * meta 列允许的键（Vben 路由 meta 的子集，设计文档 §8.2）；title、icon、order 存在各自的列中。
     *
     * @var list<string>
     */
    public const array META_KEYS = [
        'activePath', 'activeIcon', 'keepAlive', 'affixTab',
        'hideInMenu', 'hideChildrenInMenu', 'hideInBreadcrumb', 'hideInTab',
        'badge', 'badgeType', 'badgeVariants', 'iframeSrc', 'link',
    ];

    /**
     * 上级节点。
     *
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 下级节点。
     *
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * 权限码的格式是否合法（NAME_PATTERN）。
     */
    public static function isValidName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    /**
     * 节点自身是否启用（不看祖先；祖先停用时是否生效见 EffectivePermissions）。
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
            'parent_id' => 'integer',
            'type' => PermissionType::class,
            'sort' => 'integer',
            'status' => Status::class,
            'meta' => 'array',
            'is_builtin' => 'boolean',
        ];
    }
}
