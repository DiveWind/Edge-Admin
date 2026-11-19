# System

内核模块，目录 `app/Core/System`。结构与规则见设计文档 §4.3、§5。

## 职责

- 后台用户（`Models/User`，多态别名 `user`）与账号状态（`Enums/Status`：启用 / 停用，用户、角色、菜单节点共用）；
- 认证（设计文档 §7）：Fortify 的登录校验、修改资料、修改密码、重置密码使用本模块的 Action；登录与两步验证的限流器；
  找回密码邮件中的前端链接（均在 `SystemServiceProvider` 中配置）；
- 停用账号、修改密码时让已登录的会话与 token 失效；
- 角色与菜单权限（设计文档 §8.1、§8.2）：`Models/Role`、`Models/Permission`（继承 spatie 的模型，菜单树建在 `permissions` 表上，
  节点类型见 `Enums/PermissionType`）、菜单与权限节点的扩展点 `MenuRegistry`、同步命令 `core:sync-permissions`、
  有效权限码 `PermissionRegistry` 与 `Gate::before`；
- 角色管理（设计文档 §6.5、§8.5、§8.6）：`Policies/RolePolicy` 与角色的 Action（下文“角色管理”）；
- 用户管理（设计文档 §6.5、§8.5、§8.6）：`Policies/UserPolicy` 与用户的 Action（下文“用户管理”）；
- 菜单管理（设计文档 §6.5、§8.2、§8.6）：`Policies/PermissionPolicy`、节点的 Action 与 `Queries/PermissionTree`（下文“菜单管理”）；
- 数据权限（设计文档 §8.4）：扩展点 `DataScopeProvider` / `DataScopeRegistry`、默认范围 `all` 与 `self`、模型 trait `HasDataScope`（下文“数据权限”）。

不负责：认证接口与管理接口的路由与响应格式（Fortify 的路由挂在后台入口下，见 `config/fortify.php`；管理接口在 `app/Http/Admin/System`）。
字典、参数、附件等在后续接入。

## 对外提供

| 类 | 用途 |
|---|---|
| `Actions/AuthenticateUser` | 用户名 + 密码校验；停用账号抛出 422（`username`）。由 `Fortify::authenticateUsing()` 调用 |
| `Actions/UpdateUserProfileInformation` | 修改姓名、邮箱、头像（Fortify：`PUT /user/profile-information`） |
| `Actions/UpdateUserPassword` | 修改密码，其他会话与全部 token 失效（Fortify：`PUT /user/password`） |
| `Actions/ResetUserPassword` | 重置密码，全部会话与 token 失效（Fortify：`POST /reset-password`） |
| `Actions/DisableUser` | 停用用户：不能再登录，已登录的会话与 token 立即失效（由 `ChangeUserStatus` 调用） |
| `Actions/EnableUser` | 启用用户（由 `ChangeUserStatus` 调用） |
| `Actions/RevokeUserSessions` | 删除用户的会话（database 驱动，按 `sessions.user_id`，可保留一个）与全部 token |
| `Actions/SyncPermissions` | 把声明的节点同步到 `permissions` 表（`core:sync-permissions` 调用，规则见下文） |
| `Commands/SyncPermissionsCommand` | `php artisan core:sync-permissions`：调用 `SyncPermissions` 并输出变更（在 `SystemServiceProvider` 中登记） |
| `Contracts/MenuRegistry` | 扩展点：各模块在 Provider 的 `boot()` 中注册菜单与权限节点（下文） |
| `Data/MenuNodeData` | 已校验、已展开 `buttons` 的一个声明节点（`MenuRegistry::all()` 的元素）；`structuralMeta()` 为同步命令修正的 meta 键 |
| `Models/Permission` | 菜单与权限节点；`isValidName()`（权限码格式，声明与写接口共用）、`META_KEYS`（meta 列白名单）、`LIFELINE_NAMES`（生命线节点） |
| `Enums/PermissionType` | 5 种节点类型与按类型的规则：`canBeChildOf()` / `canContain()`（层级）、`isRoute()`（有 path）、`usesComponent()`、`requiredMetaKey()`、`allowsMetaKey()`、`routeComponent()`（`/menu/all` 的 component） |
| `Services/PermissionRegistry` | 有效权限码与菜单树（`#[Scoped]`），`Gate::before`、`/auth/codes`、`/menu/all` 共用 |
| `Enums/Status` | 通用的启用 / 停用状态，用户、角色、菜单节点共用 |
| `Models/User::isSuperAdmin()` | 是否拥有启用的 `super-admin` 角色（`Role::SUPER_ADMIN`）；`enabledRoles()` 为启用的角色 |
| `Models/Role::isSuperAdmin()` | 是否为 `super-admin` 角色本身 |
| `Actions/CreateRole`、`UpdateRole`、`DeleteRole`、`ChangeRoleStatus` | 角色的新增、修改、删除、启用 / 停用（规则见下文“角色管理”） |
| `Actions/SyncRolePermissions` | 保存角色的权限码：防提权、保留操作者管不到的授权、补齐祖先（由 CreateRole、UpdateRole 调用） |
| `Policies/RolePolicy` | 角色的授权：标准方法 + `changeStatus`、`viewFormOptions`、`viewOptions` |
| `Actions/CreateUser`、`UpdateUser`、`DeleteUser`、`ChangeUserStatus` | 用户的新增、修改、删除、启用 / 停用（规则见下文“用户管理”）；重置密码复用 `ResetUserPassword::handle()` |
| `Actions/SyncUserRoles` | 保存用户的角色：防提权、保留操作者管不到的角色、最后一个超管（由 CreateUser、UpdateUser 调用） |
| `Actions/EnsureSuperAdminRemains` | 系统至少保留一个启用的超管：加锁检查，最后一个时 422（在事务中调用） |
| `Policies/UserPolicy` | 用户的授权：标准方法 + `changeStatus`、`resetPassword`，记录级方法检查数据范围 |
| `Contracts/DataScopeProvider`、`Services/DataScopeRegistry` | 扩展点：数据范围提供者与其登记（下文“数据权限”） |
| `Actions/CreatePermission`、`UpdatePermission`、`DeletePermission` | 菜单节点的新增、修改、删除（规则见下文“菜单管理”） |
| `Queries/PermissionTree` | 菜单管理用的整棵节点树（含停用节点与按钮，一次查询组装） |
| `Policies/PermissionPolicy` | 菜单节点的授权：标准方法，删除内置节点 403 |
| `Models/Concerns/HasDataScope` | 模型 trait：`visibleTo($user)` 查询范围与 `isVisibleTo($user)` 单条判定（已用于 `User`）；`dataScopeOwnerColumns()` 声明“仅本人”的归属列 |

- 受信任的入口直接读取本模块的模型，写入调用 `Actions`；接口放在各受信任入口的同名目录：`app/Http/Admin/System`。
- 受限的入口只通过 `Contracts` 与 `Data` 访问（`php artisan core:make-contract`）。
- 停用账号的即时检查是多入口共用的中间件 `App\Http\Middleware\EnsureUserIsEnabled`（放在各入口的中间件组中）。
  会话为 database 以外的驱动时，停用后的会话由它在下一次请求时注销；修改密码后的其他会话由 Sanctum 的 `AuthenticateSession` 注销。

## 菜单与权限

### 声明节点：`MenuRegistry`

单例，只保存启动时注册的静态声明。本模块的节点在 `resources/menus.php`，业务模块同样在自己的 Provider 中注册：

```php
public function boot(MenuRegistry $menus): void
{
    $menus->register(require __DIR__.'/resources/menus.php');
}
```

格式与 Vben 的路由节点同构（`name`、`type`、`path`、`component`、`meta`、`children`），`buttons`（`键 => 标题`）展开为 `<父 name>.<键>` 的按钮节点。
注册时校验结构，声明有误直接抛出 `InvalidArgumentException`：name 小写点分、以字母开头（`Permission::isValidName()`）且全局唯一；type 合法且层级合法
（catalog / menu / embedded / link 在根或 catalog 下，button 在 menu 下）；路由节点的 path 以 `/` 开头且互不相同、按钮没有 path；
menu 必须有 component、其他类型不能有；embedded 必须有 `meta.iframeSrc`、link 必须有 `meta.link`；meta 只能有 title、icon、order 与白名单键
（`Permission::META_KEYS`），且要适用于该类型（`PermissionType::allowsMetaKey()`：按钮没有路由 meta，iframeSrc 只用于 embedded，link 只用于 link）。标题是前端的 i18n key，后端不翻译。

### 同步：`php artisan core:sync-permissions`

由 `DatabaseSeeder` 调用，部署时在 `optimize` 之后执行（设计文档 §11.4）。幂等，输出新增、修正、收编、转为自定义的节点：

1. 声明中有、数据库中没有的节点：新增并标记 `is_builtin`（上级按 name 查找）；已有同名的自定义节点：收编（标记 `is_builtin` 并按第 2 条修正）；
2. 已有节点只修正结构性字段 `type`、`path`、`component`、`meta.link`、`meta.iframeSrc`；`title`、`icon`、`sort`、`status`、`parent_id`
   与 meta 的其他键以数据库为准，管理员的调整不被覆盖；
3. 从声明中移除的内置节点：取消 `is_builtin`，转为自定义节点并保留授权；不删除任何节点；
4. 确保 `super-admin` 角色存在；
5. 清除 spatie 的权限缓存。

`path` 唯一：声明节点的 path 被不在声明中的节点占用时，命令列出冲突（哪个声明节点、哪个 path、被哪个节点占用）与处理建议，
退出码 1，不写入任何数据（检查在写入前、整次同步在一个事务中）。声明之间互换 path 时先清空要变更的 path，不会撞上唯一索引。

### 判定：`PermissionRegistry` 与 `Gate::before`

- 节点从 spatie 的 `PermissionRegistrar` 缓存读取，不额外查库；`#[Scoped]`，每个请求内复用计算结果，spatie 的缓存被清除后重新计算；
- 生效节点：节点本身及全部祖先都启用（停用向子孙级联，超管也受约束）；
- 有效权限码：普通用户要求节点本身及全部祖先都生效，且都授予了用户的**启用**角色；超管为全部生效节点；
- `Gate::before`（`SystemServiceProvider`）：无参数的调用且 ability 是已登记的权限码 → 按有效权限码返回 true / false；
  带参数的调用（Policy：`can('update', $model)`）与其他 ability → null，交给 Policy（节点恰好叫 `update` 也不会绕过 Policy）
  （超管同样受 Policy 中记录级规则的约束）。spatie 自带的 Gate 检查已关闭（`config/permission.php`）；
- `menuTreeFor()`：`/menu/all` 的节点树（不含按钮，没有可见子节点的目录剔除，同级按 sort、id 排序），输出格式由后台入口的 `MenuRouteResource` 负责。

判定一律走 `can()` / Gate / Policy；spatie 的判定 API（`hasRole()`、`hasPermissionTo()` 等）与 `role:` / `permission:` 中间件被 PHPStan 与 `tests/Arch` 禁止。

## 菜单管理

接口见设计文档 §6.5（`/system/menus`），由后台入口的 `MenuController` 调用本模块的 Action 与 `Queries/PermissionTree`；
按类型的字段校验（必填 / 不允许提交）在后台入口的 `MenuRequest` 中。403 / 422 按 §6.1 分工：

| 规则 | 结果 | 在哪 |
|---|---|---|
| 没有对应的权限码 | 403 | `PermissionPolicy` |
| 删除内置节点（超管也一样） | 403 | `PermissionPolicy::delete()` |
| 内置节点改 `name` / `type` / `path` / `component` / `meta.link` / `meta.iframeSrc` | 422（对应字段） | `UpdatePermission` |
| 上级是自己或自己的子孙 | 422（`parent_id`） | `UpdatePermission` |
| 类型层级不合法（新增；修改时移动了上级） | 422（`parent_id`） | `CreatePermission`、`UpdatePermission` |
| 修改类型后不能挂在现在的上级下，或容纳不了现有的子节点 | 422（`type`） | `UpdatePermission` |
| 停用 `system.menu`、`system.menu.update` 或它们（当前）的祖先 | 422（`status`） | `UpdatePermission` |
| 把这两个节点（或其祖先）移到停用的节点下 | 422（`parent_id`） | `UpdatePermission` |
| 删除有子节点的节点 | 422（只有 `message`） | `DeletePermission` |
| 权限码、path 重复 | 422（`name` / `path`） | 表单校验；并发时 `CreatePermission::save()` 捕获唯一索引冲突 |

- 新增的节点是自定义节点（`is_builtin = false`），guard 为 `web`；内置节点只能由同步命令新增，管理员可以调整它的 `title`、`icon`、`sort`、
  `status`、`parent_id` 与其余 meta 键，再次同步不会覆盖；
- 生命线只拦截本次修改造成的失效（修改前就已失效的不拦，以免挡住修复），祖先按修改后的上级计算：`system.menu` 被移到别的目录下后，
  新目录不能停用，原来的 `system` 可以停用；
- 删除节点时授权由 spatie 在 `deleting` 事件中解除，外键 `cascadeOnDelete` 兜底；
- 写入后 spatie 自动清除权限缓存（`Permission` 的 saved / deleted 事件），`/menu/all`、`/auth/codes` 与 `Gate::before` 立即按新数据计算。

## 角色管理

接口见设计文档 §6.5（`/system/roles`），由后台入口的 `RoleController` 等调用本模块的 Action；403 / 422 按 §6.1 分工：

| 规则 | 结果 | 在哪 |
|---|---|---|
| 没有对应的权限码 | 403 | `RolePolicy` |
| 删除 `super-admin` 角色 | 403 | `RolePolicy::delete()` |
| `super-admin` 改标识 / 停用 / 提交 `permissions` | 422（`name` / `status` / `permissions`） | `UpdateRole`、`ChangeRoleStatus` |
| 删除已分配给用户的角色 | 422（只有 `message`） | `DeleteRole` |
| 授予自己没有、且角色原本也没有的权限码（非超管） | 422（`permissions`） | `SyncRolePermissions` |
| 选用（改为）自己不可授予的数据范围（非超管；保持原值不拦） | 422（`data_scope`） | `CreateRole::ensureGrantableDataScope()`（`CreateRole`、`UpdateRole` 调用） |
| 标识重复 | 422（`name`） | 表单校验；并发时 `CreateRole::save()` 捕获唯一索引冲突 |

保存权限码（`SyncRolePermissions`）：

1. 防提权：非超管提交的权限码中，不在操作者有效权限码里、且角色原本也没有的 → 422；超管不受限；
2. 操作者管不到的授权保持不变：角色原本就有、但不在操作者有效权限码中的（非超管自己没有的码；对超管是停用节点的授权，它们不出现在授权树中），
   无论是否提交都保留；
3. 补齐每个节点的全部祖先（`PermissionRegistry::withAncestors()`），再 `syncPermissions()`；spatie 随之清除权限缓存，
   `/auth/codes`、`/menu/all` 立即按新授权计算。停用角色同样立即生效。

- 非超管不能修改、启停 `super-admin` 角色（`RolePolicy::update()`、`changeStatus()`，403）。

guard 统一为 `web`（`User::GUARD`）：`User::guardName()` 固定返回它，`CreateRole` 显式写入 `guard_name`；
否则 spatie 会取当前请求的 guard（`auth:sanctum` 下为 `sanctum`），找不到角色与权限。

## 用户管理

接口见设计文档 §6.5（`/system/users`），由后台入口的 `UserController`、`UserStatusController`、`UserPasswordController` 调用本模块的 Action；
403 / 422 按 §6.1 分工：

| 规则 | 结果 | 在哪 |
|---|---|---|
| 没有对应的权限码；超出数据范围 | 403 | `UserPolicy` |
| 删除 / 停用自己；在用户管理中重置自己的密码 | 403 | `UserPolicy::delete()`、`changeStatus()`、`resetPassword()` |
| 非超管编辑、重置密码、启停、删除超管用户 | 403 | `UserPolicy` |
| 修改接口中提交 `status: disabled` 停用自己 | 422（`status`） | `ChangeUserStatus` |
| 最后一个启用的超管：删除 / 停用 / 去掉超管角色 | 422（只有 `message` / `status` / `roles`） | `EnsureSuperAdminRemains`（`DeleteUser`、`ChangeUserStatus`、`SyncUserRoles` 调用） |
| 非超管授予或收回 `super-admin`；分配管不到的角色（权限码或数据范围超出自身） | 422（`roles`） | `SyncUserRoles` |
| 用户名、邮箱重复 | 422（`username` / `email`） | 表单校验；并发时 `CreateUser::save()` 捕获唯一索引冲突 |

- 用户名统一存为小写（`CreateUser::only()`，表单也先转小写再校验唯一）；`created_by` 写入操作者；
- 分配角色（`SyncUserRoles`，spatie 的 `syncRoles()`）：非超管只能分配、移除权限码全部在自己有效权限码之内、且数据范围可授予的角色；
  用户原本就有、但操作者管不到的角色（含 `super-admin`）无论是否提交都保留；超管不受限；
- 最后一个启用的超管（`EnsureSuperAdminRemains`）：在写入的同一事务中先对 `super-admin` 角色那一行 `lockForUpdate()`，
  把删除、停用、去掉超管角色串行化，再按数据库的当前状态数启用的超管；不在事务中调用时抛出 `LogicException`；
- 停用走 `DisableUser`（会话与 token 立即失效），启用走 `EnableUser`；重置密码（`ResetUserPassword::handle()`）、删除（`DeleteUser`）
  同样吊销对方的会话与 token。

## 数据权限

角色决定能做什么，数据范围决定能看哪些数据（设计文档 §8.4）。每个角色选用一个范围（`roles.data_scope`，新建默认 `self`）。

- 扩展点：实现 `Contracts/DataScopeProvider`（`key()`、`label()`、`constrain()`、`contains()`，两者同一规则，实现必须无状态），
  在模块 Provider 的 `boot()` 中注册，角色表单的 `/system/roles/data-scopes` 自动出现：

  ```php
  public function boot(DataScopeRegistry $scopes): void
  {
      $scopes->register(new DepartmentDataScope);
  }
  ```

- `Services/DataScopeRegistry`：单例（`#[Singleton]`），只保存注册的提供者；key 重复或不合法时抛出 `InvalidArgumentException`。
  `grantableKeysFor($user)` / `canGrant($user, $key)` 是数据范围的防提权（§8.5）：超管或任一启用角色为 `all` → 全部已登记范围，
  否则为各启用角色的范围；角色管理、分配角色、`/system/roles/data-scopes` 的选项共用。
  本模块注册 `all`（`AllDataScope`，不限制）与 `self`（`SelfDataScope`，按模型声明的归属列，见下）；
- `Models/Concerns/HasDataScope`：`Model::query()->visibleTo($user)` 与 `$record->isVisibleTo($user)`（Policy 复用），规则：
  1. 超管，或任一启用角色的范围是 `all` → 不限制；
  2. 否则各启用角色的范围 OR 并集（每个提供者收到选用它的启用角色，条件包在独立的括号组中）；
  3. 没有启用角色，或范围 key 未登记（该角色不贡献范围）→ 拒绝：查询加 `1 = 0`，单条判定为 false。

- “仅本人”的归属列：`HasDataScope::dataScopeOwnerColumns()`，任一列的值等于用户 id 即属于该用户（多列为 OR），默认 `['created_by']`；
  `User` 覆盖为 `['created_by', 'id']`（本人与自己创建的用户）。`SelfDataScope` 的 `constrain()` 与 `contains()` 读取同一组列。
  本人可见不等于可操作：删除、停用自己仍由 `UserPolicy` 拒绝。

所有查询入口（列表、详情、下拉选项、导出、统计）都要经过它；使用 `self` 范围的模型需要归属列（默认 `created_by`，由 Action 写入）。

## 依赖 Core 的能力

无。`Contracts/HasLabel` 是本模块提供给 Core 与业务模块的枚举接口。

## 配置

`config/permission.php`：模型指向本模块的 `Permission`、`Role`；`register_permission_check_method = false`；`register_octane_reset_listener = true`。

`config/core.php` 的 `auth`：`login_rate_limit`（每分钟登录次数）、`frontend_url` 与 `reset_password_path`（重置密码的前端页面）。

## 生成命令

```bash
php artisan core:make-model <名称> --module=System --core       # 模型、迁移、工厂、Policy，登记多态别名
php artisan core:make-action <名称> --module=System --core      # 写操作
php artisan core:make-query <名称> --module=System --core       # 复杂查询、统计
php artisan core:make-enum <名称> --module=System --core        # 枚举（实现 HasLabel）
php artisan core:make-contract <名称> --module=System --core    # 向受限入口开放的契约
php artisan core:make-controller <名称> --module=System --core --model=<模型>   # 后台接口
```
