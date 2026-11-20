# System

内核模块，目录 `app/Core/System`。结构与规则见设计文档 §4.3、§5。

## 职责

- 后台用户（`Models/User`，多态别名 `user`）与账号状态（`Enums/Status`：启用 / 停用，用户、角色、菜单节点共用）；
- 认证（设计文档 §7）：Fortify 的登录校验、修改资料、修改密码、重置密码使用本模块的 Action；登录与两步验证的限流器（登录被限流时派发 `Lockout`）；
  找回密码邮件中的前端链接（均在 `SystemServiceProvider` 中配置）；
- 停用账号、修改密码时让已登录的会话与 token 失效；
- 角色与菜单权限（设计文档 §8.1、§8.2）：`Models/Role`、`Models/Permission`（继承 spatie 的模型，菜单树建在 `permissions` 表上，
  节点类型见 `Enums/PermissionType`）、节点的扩展点 `MenuRegistry`、同步命令 `core:sync-permissions`、
  有效权限码 `EffectivePermissions` 与 `Gate::before`；
- 角色管理（设计文档 §6.5、§8.5、§8.6）：`Policies/RolePolicy` 与角色的 Action（下文“角色管理”）；
- 用户管理（设计文档 §6.5、§8.5、§8.6）：`Policies/UserPolicy` 与用户的 Action（下文“用户管理”）；
- 菜单管理（设计文档 §6.5、§8.2、§8.6）：`Policies/PermissionPolicy`、节点的 Action 与 `Queries/PermissionTree`（下文“菜单管理”）；
- 数据权限（设计文档 §8.4）：扩展点 `DataScopeProvider` / `DataScopeRegistry`、默认范围 `all` 与 `self`、模型 trait `HasDataScope`（下文“数据权限”）；
- 字典（设计文档 §6.7）：`Models/DictType`、`Models/DictItem`、扩展点 `DictRegistry` 与同步命令 `core:sync-dicts`、读取服务 `Dictionary`、校验规则 `InDict`（下文“字典”）；
- 系统参数（设计文档 §6.7）：`Models/Setting`、扩展点 `SettingRegistry` 与同步命令 `core:sync-settings`、读取服务 `Settings`、本模块的参数分组 `resources/settings.php`（下文“系统参数”）；
- 枚举选项（设计文档 §6.7）：显式白名单 `Services/EnumRegistry`（下文“枚举选项”）；
- 附件（设计文档 §6.8）：`Models/Attachment`、上传校验 `Rules/AllowedUpload`、归属校验 `Rules/OwnedAttachment`、绑定 `Actions/SyncAttachments` 与
  模型 trait `Models/Concerns/HasAttachments`、访问权委托 `Policies/AttachmentPolicy`、孤儿清理、头像（下文“附件”）；
- 异步导出（设计文档 §6.10）：扩展点 `Contracts/Exporter` / `Services/ExporterRegistry`、导出任务 `Models/Export` 与队列任务 `Jobs/RunExport`、
  用户列表的导出 `Exporters/UserExporter`（与列表共用 `Queries/UserList`）、文件存为私有附件（下文“异步导出”）。

不负责：认证接口与管理接口的路由与响应格式（Fortify 的路由挂在后台入口下，见 `config/fortify.php`；管理接口在 `app/Http/Admin/System`）；
附件的下载、预览路由与签名 URL 的签发（入口的事，后台入口见 `app/Http/Admin/System/Controllers/Attachment*Controller` 与 `AttachmentResource`）。

## 对外提供

| 类 | 用途 |
|---|---|
| `Actions/AuthenticateUser` | 用户名 + 密码校验；停用账号抛出 422（`username`），抛出前派发 `Failed`（只带用户名，供 Monitor 的登录日志）。由 `Fortify::authenticateUsing()` 调用 |
| `Actions/UpdateUserProfileInformation` | 修改姓名、邮箱、头像（`avatar_id`，附件 id；Fortify：`PUT /user/profile-information`） |
| `Actions/UpdateUserPassword` | 修改密码，其他会话与全部 token 失效（Fortify：`PUT /user/password`） |
| `Actions/ResetUserPassword` | 重置密码，全部会话与 token 失效（Fortify：`POST /reset-password`） |
| `Actions/DisableUser` | 停用用户：不能再登录，已登录的会话与 token 立即失效（由 `ChangeUserStatus` 调用） |
| `Actions/RevokeUserSessions` | 删除用户的会话（database 驱动，按 `sessions.user_id`，可保留一个）与全部 token |
| `Actions/SyncPermissions` | 把声明的节点同步到 `permissions` 表（`core:sync-permissions` 调用，规则见下文） |
| `Commands/SyncPermissionsCommand` | `php artisan core:sync-permissions`：调用 `SyncPermissions` 并输出变更（在 `SystemServiceProvider` 中登记） |
| `Services/MenuRegistry` | 扩展点：各模块在 Provider 的 `boot()` 中注册节点（下文） |
| `Data/MenuNodeData` | 已校验、已展开 `buttons` 的一个声明节点（`MenuRegistry::all()` 的元素）；`structuralMeta()` 为同步命令修正的 meta 键 |
| `Models/Permission` | 节点（菜单树的一行，URL 资源为 `menus`）；`isValidName()`（权限码格式，声明与写接口共用）、`META_KEYS`（meta 列白名单）、`LIFELINE_NAMES`（生命线节点） |
| `Enums/PermissionType` | 5 种节点类型与按类型的规则：`canBeChildOf()` / `canContain()`（层级）、`isRoute()`（有 path）、`usesComponent()`、`requiredMetaKey()`、`allowsMetaKey()` |
| `Services/EffectivePermissions` | 请求级的有效权限码与菜单树计算器（`#[Scoped]`，不是注册表），`Gate::before`、`/auth/codes`、`/menu/all` 共用 |
| `Services/PermissionHierarchy` | 菜单树的纯计算：`chain()`、`isSelfOrDescendant()`、`everyInChain()`、`isEffective()`（节点及全部祖先都启用）、`withAncestors()`、`tree()`；有效权限码、菜单管理与节点树共用（脏数据按不生效处理） |
| `Enums/Status` | 通用的启用 / 停用状态，用户、角色、菜单节点共用 |
| `Models/User::isSuperAdmin()` | 是否拥有启用的 `super-admin` 角色（`Role::SUPER_ADMIN`）；`enabledRoles()` 为启用的角色 |
| `Models/Role::isSuperAdmin()` | 是否为 `super-admin` 角色本身 |
| `Actions/CreateRole`、`UpdateRole`、`DeleteRole`、`ChangeRoleStatus` | 角色的新增、修改、删除、启用 / 停用（规则见下文“角色管理”） |
| `Actions/SyncRolePermissions` | 保存角色的权限码：防提权、保留操作者管不到的授权、补齐祖先（由 CreateRole、UpdateRole 调用） |
| `Queries/RoleList` | 角色列表的查询（筛选与排序白名单） |
| `Policies/RolePolicy` | 角色的授权：标准方法 + `changeStatus`、`viewFormOptions`（角色表单的授权树与数据范围） |
| `Actions/CreateUser`、`UpdateUser`、`DeleteUser`、`ChangeUserStatus` | 用户的新增、修改、删除、启用 / 停用（规则见下文“用户管理”）；重置密码复用 `ResetUserPassword::handle()` |
| `Actions/SyncUserRoles` | 保存用户的角色：防提权、保留操作者管不到的角色、最后一个超管（由 CreateUser、UpdateUser 调用） |
| `Services/SuperAdminGuard` | 系统至少保留一个启用的超管：加锁检查，最后一个时 422（在事务中调用）；`lock()` 是可能减少启用超管的事务的第一条语句（锁顺序） |
| `Services/Manageability` | “管得到”的统一判定（§8.5）：`canManageRole()`、`canManageUser()`、可授予的权限码与数据范围、`ensureGrantableDataScope()`（422）；Policy（403）与 `SyncRolePermissions`、`SyncUserRoles`、`CreateRole`、`UpdateRole`（422）共用 |
| `Events/UserDeleting`、`Events/UserDeleted` | Core 事件：`DeleteUser` 在事务中删除前派发 `UserDeleting`（同步清理属于该用户的数据），提交后派发 `UserDeleted`；本模块的 `Listeners/DeleteExportsOfUser` 监听前者 |
| `Policies/UserPolicy` | 用户的授权：标准方法 + `changeStatus`、`resetPassword`，记录级方法检查数据范围 |
| `Contracts/DataScopeProvider`、`Services/DataScopeRegistry` | 扩展点：数据范围提供者与其登记（下文“数据权限”） |
| `Actions/CreatePermission`、`UpdatePermission`、`DeletePermission` | 菜单节点的新增、修改、删除（规则见下文“菜单管理”） |
| `Queries/PermissionTree` | 菜单管理用的整棵节点树（含停用节点与按钮，一次查询组装） |
| `Policies/PermissionPolicy` | 菜单节点的授权：标准方法，删除内置节点 403 |
| `Models/Concerns/HasDataScope` | 模型 trait：`visibleTo($user)` 查询范围与 `isVisibleTo($user)` 单条判定（已用于 `User`）；`dataScopeOwnerColumns()` 声明“仅本人”的归属列 |
| `Services/DictRegistry` | 扩展点：各模块在 Provider 的 `boot()` 中声明字典类型与初始字典项（下文“字典”） |
| `Queries/DictTypeList`、`Queries/DictItemList` | 字典类型、某个类型下字典项的列表查询（筛选与排序白名单） |
| `Actions/SyncDicts`、`Commands/SyncDictsCommand` | `php artisan core:sync-dicts`：把声明的字典同步到 `dict_types`、`dict_items` |
| `Actions/CreateDictType`、`UpdateDictType`、`DeleteDictType`、`CreateDictItem`、`UpdateDictItem`、`DeleteDictItem` | 字典类型与字典项的新增、修改、删除（内置的保护见下文） |
| `Services/Dictionary` | 字典的读取（启用类型下的启用项，整体缓存）：`options()`、`many()`、`values()`、`has()`、`flush()` |
| `Rules/InDict` | 校验规则：值必须是某个字典类型下的启用项；修改记录时用 `new InDict('<类型>')->allowing($record?->field)` 放行当前值（即使已停用，保持原值不拦），改成其他值仍只接受启用项 |
| `Policies/DictTypePolicy`、`DictItemPolicy` | 字典的授权：标准方法，删除内置的 403 |
| `Services/SettingRegistry` | 扩展点：各模块在 Provider 的 `boot()` 中注册系统参数分组（下文“系统参数”） |
| `Actions/SyncSettings`、`Commands/SyncSettingsCommand` | `php artisan core:sync-settings`：把声明的参数同步到 `settings` |
| `Actions/UpdateSettings` | 修改参数的值（按类型转换，null 恢复默认值） |
| `Services/Settings` | 系统参数的读取（给代码用的唯一方式）：`get()`、`string()`、`integer()`、`float()`、`boolean()`、`array()`、`public()` |
| `Enums/SettingType` | 参数类型 `string`、`text`、`int`、`float`、`bool`、`json`：校验规则、输入转换、类型判断 |
| `Policies/SettingPolicy` | 系统参数的授权：查看、修改值；新增、删除一律拒绝 |
| `Services/EnumRegistry` | 可下发给前端的枚举（显式白名单，`/options/enums`；各模块的 `resources/enums.php`） |
| `App\Support\Enums\HasColor`（Support） | 枚举的语义色（可选）：`color()` 为 antd 预设色名或 hex，`/options/enums` 据此输出 `color`；`Status` 已实现 |
| `Models/Attachment` | 附件：`PREVIEWABLE_MIME_TYPES`（可预览、可作头像的图片白名单）、`isBound()`、`isBoundTo()`、`isPreviewable()`、`asciiFileName()`（下载的 ASCII 回退文件名）；`Prunable`（孤儿清理）；删除后在事务提交后删除文件 |
| `Models/Concerns/HasAttachments` | 模型 trait：`attachments()`、`attachmentsIn()`、`attachmentIn()`；记录删除时一并删除附件（已用于 `User` 的头像 `avatar()`） |
| `Actions/StoreAttachment` | 保存文件并登记为附件（随机文件名、服务端探测 MIME、sha256；上传时未绑定，导出文件直接绑定到导出记录），附件元数据规则的唯一实现 |
| `Actions/SyncAttachments` | 把附件绑定到记录的某个用途上，替换、移除的删除；加锁复核“未绑定或已在这里”（422） |
| `Actions/DeleteAttachment` | 删除附件，文件在事务提交后删除 |
| `Queries/AttachmentList` | 附件管理的列表查询（`visibleTo`、不含私有绑定的附件、筛选与排序白名单） |
| `Actions/DeleteUnboundAttachment` | 附件管理的删除与孤儿清理：锁内复核仍未绑定才删除（管理时已被绑定 → 422，清理时跳过） |
| `Rules/AllowedUpload` | 上传校验：内容 MIME 推断的扩展名与客户端扩展名都在白名单中、大小上限（系统参数 `attachment.*`） |
| `Rules/OwnedAttachment` | 引用附件的归属校验：自己上传的未绑定附件，或这条记录已有的附件；可限定 MIME |
| `Contracts/PrivateAttachable` | 标记接口：绑定在实现它的记录上的附件只跟随记录（附件管理不列出、管理员不能访问），`Export` 已实现 |
| `Contracts/Exporter`、`Services/ExporterRegistry` | 扩展点：导出类（显示名、权限码、列、按“操作者 + 列表参数”构造查询、逐行映射）与其登记（各模块的 `resources/exporters.php`，下文“异步导出”） |
| `Queries/ExportList` | “我的导出”的列表查询（只含本人的，筛选与排序白名单） |
| `Queries/UserList` | 用户列表的查询（query-builder 白名单 + `visibleTo`），列表接口与 `UserExporter` 共用 |
| `Exporters/UserExporter` | 用户列表的导出（导出名 `user`，权限码 `system.user.export`） |
| `Queries/RoleOptions` | 角色下拉（启用角色 `[{ value, label }]`）：用户表单 `/system/users/role-options` 与公告表单共用，授权由使用它的资源的 `viewFormOptions` 决定 |
| `Actions/CreateExport` | 发起导出：同步校验参数、并发上限（锁用户行，422）、入队 `RunExport` |
| `Actions/GenerateExport`、`Jobs/RunExport` | 生成文件：复核权限、`lazy()` 分块、按发起时的语言、防公式注入（`Services/ExportWriter`）、存为附件、派发 `Events/ExportFinished` |
| `Actions/FailExport`、`DeleteExport`、`DeleteUserExports` | 标记失败；删除（排队、进行中的即取消）；删除用户的全部导出（用户删除前） |
| `Policies/ExportPolicy` | 导出只属于发起人：查看、下载、删除只限本人 |
| `Events/ExportFinished` | 导出完成或失败（Message 据此通知发起人） |
| `Policies/AttachmentPolicy` | 附件的授权：上传只需登录；访问权委托给绑定对象；附件管理按 `system.attachment` 与数据范围；已绑定的不能删 |
| `App\Support\Enums\HasLabel`（Support） | 枚举的显示名（必须，`tests/Arch` 强制）：`label()` 按当前语言翻译 |
| `Commands/SyncCommand` | `php artisan core:sync`：依次执行 `core:sync-permissions`、`core:sync-dicts`、`core:sync-settings`，任何一个失败即停止（非 0 退出）；`DatabaseSeeder` 与部署使用 |

- 内部实现（不对外）：`Services/{AllDataScope, SelfDataScope}`（默认的数据范围）、`Services/ExportWriter`、`Observers/*`、`Data/{Dict*,Setting*}Data`、`Enums/Export*`、`Listeners/DeleteExportsOfUser`。
- 唯一冲突兜底（`App\Support\Database\UniqueConstraint`）与业务规则异常（`App\Support\Exceptions\BusinessRuleViolation`）在 Support，本模块的 Action 都用它们（设计文档 §5.4）。
- 受信任的入口直接读取本模块的模型，写入调用 `Actions`；列表的查询调用 `Queries/<模型>List`；接口放在各受信任入口的同名目录：`app/Http/Admin/System`。
- 受限的入口只通过 `Contracts` 与 `Data` 访问（`php artisan core:make-contract`）。
- 停用账号的即时检查是多入口共用的中间件 `App\Http\Middleware\EnsureUserIsEnabled`（放在各入口的中间件组中）。
  会话为 database 以外的驱动时，停用后的会话由它在下一次请求时注销；修改密码后的其他会话由 Sanctum 的 `AuthenticateSession` 注销。

**URL 资源 ↔ 模型**（Http 层按 URL 资源命名，领域层按模型命名，设计文档 §4.4）：

| URL 资源 | Http 层 | 模型 / 领域层 |
|---|---|---|
| `/system/menus` | `MenuController`、`MenuRequest`、`MenuResource`；`/menu/all` 的 `MenuRouteResource` | `Permission`、`PermissionPolicy`、`Create/Update/DeletePermission`、`PermissionTree` |
| `/system/users/role-options` | `UserRoleOptionsController`（`UserPolicy::viewFormOptions`） | `Role`、`Queries/RoleOptions` |
| `/attachments`（个人）、`/system/attachments`（管理） | `MyAttachmentController`、`MyAttachmentRequest`；`AttachmentController`、`AttachmentIndexRequest` | `Attachment`、`AttachmentPolicy`、`StoreAttachment`、`AttachmentList` |
| `/system/users/export` | `ExportUsersController`、`ExportUsersRequest` | `CreateExport`、`UserExporter`（导出名 `user`） |
| `/exports`（我的导出） | `ExportController` | `Export`、`ExportPolicy`、`ExportList` |

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

由 `core:sync` 调用（`DatabaseSeeder` 与部署时在 `optimize` 之后执行，设计文档 §11.4），也可单独执行。幂等，输出新增、修正、收编、转为自定义的节点：

1. 声明中有、数据库中没有的节点：新增并标记 `is_builtin`（上级按 name 查找）；已有同名的自定义节点：收编（标记 `is_builtin` 并按第 2 条修正）；
2. 已有节点只修正结构性字段 `type`、`path`、`component`、`meta.link`、`meta.iframeSrc`；`title`、`icon`、`sort`、`status`、`parent_id`
   与 meta 的其他键以数据库为准，管理员的调整不被覆盖；
3. 从声明中移除的内置节点：取消 `is_builtin`，转为自定义节点并保留授权；不删除任何节点；
4. 确保 `super-admin` 角色存在；
5. 清除 spatie 的权限缓存。

`path` 唯一：声明节点的 path 被不在声明中的节点占用时，命令列出冲突（哪个声明节点、哪个 path、被哪个节点占用）与处理建议，
退出码 1，不写入任何数据（检查在写入前、整次同步在一个事务中）。声明之间互换 path 时先清空要变更的 path，不会撞上唯一索引。

### 判定：`EffectivePermissions` 与 `Gate::before`

- 节点从 spatie 的 `PermissionRegistrar` 缓存读取，不额外查库；`#[Scoped]`，每个请求内复用计算结果，spatie 的缓存被清除后重新计算；
  队列 worker 中每个任务开始前（`JobProcessing`）清掉 registrar 的内存集合，任务按最新的授权判定（设计文档 §10.3）；
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
| 非超管让不生效的节点生效（启用；移出停用的祖先） | 422（`status` / `parent_id`） | `UpdatePermission`（比较修改前后每个节点的生效状态） |
| 新增时上级已不存在 | 422（`parent_id`） | `CreatePermission`（对上级加共享锁，与 `DeletePermission` 的行锁互斥） |
| 删除有子节点的节点 | 422（只有 `message`） | `DeletePermission` |
| 权限码、path 重复 | 422（`name` / `path`） | 表单校验；并发时 `UniqueConstraint::save()` 捕获唯一索引冲突（新增、修改都是） |

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
| 非超管修改、删除、启停管不到的角色（已授予的码不全在自己的有效权限码内，或数据范围不可授予；含停用角色、停用节点上的码） | 403 | `RolePolicy`（`Services/Manageability`） |
| `super-admin` 改标识 / 停用 / 提交 `permissions` | 422（`name` / `status` / `permissions`） | `UpdateRole`、`ChangeRoleStatus` |
| 删除已分配给用户的角色 | 422（只有 `message`） | `DeleteRole` |
| 授予自己没有、且角色原本也没有的权限码（非超管） | 422（`permissions`） | `SyncRolePermissions` |
| 选用（改为）自己不可授予的数据范围（非超管） | 422（`data_scope`） | `Manageability::ensureGrantableDataScope()`（`CreateRole`、`UpdateRole` 调用） |
| 标识重复 | 422（`name`） | 表单校验；并发时 `UniqueConstraint::save()` 捕获唯一索引冲突（新增、修改都是） |

保存权限码（`SyncRolePermissions`）：

1. 防提权：非超管提交的权限码中，不在操作者有效权限码里、且角色原本也没有的 → 422；超管不受限；
2. 操作者管不到的授权保持不变：角色原本就有、但不在操作者有效权限码中的（非超管自己没有的码；对超管是停用节点的授权，它们不出现在授权树中），
   无论是否提交都保留（非超管只能修改管得到的角色，对非超管这条只在并发改动时起作用）；
3. 补齐每个节点的全部祖先（`EffectivePermissions::withAncestors()`），再 `syncPermissions()`；spatie 随之清除权限缓存，
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
| 非超管编辑、重置密码、启停、删除管不到的用户（他的某个角色，含停用的，管不到）；强制下线他的会话与 token | 403 | `UserPolicy`、Monitor 的 `SessionPolicy`、`PersonalAccessTokenPolicy`（`Services/Manageability`） |
| 修改接口中提交 `status: disabled` 停用自己 | 422（`status`） | `ChangeUserStatus` |
| 最后一个启用的超管：删除 / 停用 / 去掉超管角色 | 422（只有 `message` / `status` / `roles`） | `SuperAdminGuard`（`DeleteUser`、`ChangeUserStatus`、`SyncUserRoles` 调用） |
| 非超管授予或收回 `super-admin`；分配管不到的角色（权限码或数据范围超出自身） | 422（`roles`） | `SyncUserRoles` |
| 用户名、邮箱重复 | 422（`username` / `email`） | 表单校验；并发时 `UniqueConstraint::save()` 捕获唯一索引冲突（新增、修改都是） |

- 用户名统一存为小写（`CreateUser::only()`，表单也先转小写再校验唯一）；`created_by` 写入操作者；
- 分配角色（`SyncUserRoles`，spatie 的 `syncRoles()`）：非超管只能分配、移除权限码全部在自己有效权限码之内、且数据范围可授予的角色；
  用户原本就有、但操作者管不到的角色（含 `super-admin`）无论是否提交都保留；超管不受限；
- 最后一个启用的超管（`SuperAdminGuard`）：可能减少启用超管的事务（`DeleteUser`、`ChangeUserStatus` 停用、`UpdateUser` 提交了 `roles`
  或停用）**第一条语句**是对 `super-admin` 角色那一行 `lockForUpdate()`（`lock()`），之后才读写用户行——锁顺序一致，避免死锁，
  MySQL REPEATABLE READ 下快照也不会早于拿锁；计数用加锁读（`sharedLock()`）读最新提交的版本。不在事务中调用时抛出 `LogicException`；
- 停用走 `DisableUser`（会话与 token 立即失效），启用由 `ChangeUserStatus` 直接写入；重置密码（`ResetUserPassword::handle()`）、删除（`DeleteUser`）
  同样吊销对方的会话与 token。

## 数据权限

角色决定能做什么，数据范围决定能看哪些数据（设计文档 §8.4）。每个角色选用一个范围（`roles.data_scope`，新建默认 `self`）。

- 扩展点：实现 `Contracts/DataScopeProvider`（`key()`、`label()`、`constrain()`、`contains()`，两者同一规则，实现必须无状态），
  列入模块的 `resources/data-scopes.php`（`return [DepartmentDataScope::class];`，由容器解析）并在 Provider 的 `boot()` 中注册，
  角色表单的 `/system/roles/data-scopes` 自动出现：

  ```php
  public function boot(DataScopeRegistry $scopes): void
  {
      $scopes->register(require __DIR__.'/resources/data-scopes.php');
  }
  ```

- `Services/DataScopeRegistry`：单例（`#[Singleton]`），只保存注册的提供者；key 重复或不合法时抛出 `InvalidArgumentException`。
  `isUnrestrictedFor($user)`：超管或任一启用角色为 `all`（不受数据范围限制，`HasDataScope` 与 Monitor 的日志共用）；
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

## 字典

接口见设计文档 §6.7（`/system/dict-types`、`/system/dict-items`、`/options/dicts`）。字典给“管理员可以增删改、代码只校验是否合法”的值列表用；
代码要按值分支的固定集合用枚举。

- 声明（扩展点 `DictRegistry`，单例，只保存启动时注册的静态声明）：模块在 Provider 的 `boot()` 中注册本模块用到的类型，类型编码以模块名开头。
  本模块目前没有内置的字典类型。

  ```php
  // ContentServiceProvider
  public function boot(DictRegistry $dicts): void
  {
      $dicts->register(require __DIR__.'/resources/dicts.php');
  }

  // resources/dicts.php
  return [
      [
          'code' => 'content.article_source',
          'name' => 'content.dict.articleSource',          // i18n key 或纯文本，后端不翻译
          'items' => [
              ['value' => 'original', 'label' => 'content.dict.articleSource.original', 'color' => 'success'],
              ['value' => 'reprint', 'label' => 'content.dict.articleSource.reprint'],   // sort 默认为声明中的序号
          ],
      ],
  ];
  ```

  注册时校验：`code` 为小写点分的标识（`DictType::isValidCode()`）且不重复；`value` 为非空字符串（最长 64）且类型内不重复；`label` 必填；
  `color` 为 antd 预设色名或 `#RGB` / `#RRGGBB`（`DictItem::COLOR_PATTERN`）。
- 同步 `php artisan core:sync-dicts`（由 `core:sync` 调用）：缺的类型与字典项新增为内置；同 `code` / `value` 的自定义数据收编；
  **结构性字段只有标识**（类型的 `code`、字典项的 `value`），名称、备注、`label`、`color`、`sort`、`status` 以库为准；
  从声明中移除的内置类型（连同其内置项）、内置项转为自定义，不删除。幂等，一个事务，结束时清除缓存。
- 读取：`Services/Dictionary`（整体缓存，带版本号的键 `core:dicts:<版本>`（`App\Support\Cache\VersionedCache`），经 `Cache::memo()`；字典写入后由 `Observers/DictionaryObserver` 在事务提交后换新版本——并发读取写回的旧值只会落在旧版本的键下）。
- 校验：

  ```php
  'source' => ['required', 'string', new InDict('content.article_source')],
  ```

  只接受启用类型下启用项的 `value`（字符串或整数，按字符串比较），否则为 `validation.in`。

| 规则 | 结果 | 在哪 |
|---|---|---|
| 没有对应的权限码（`system.dict`、`.create`、`.update`、`.delete`，类型与字典项共用） | 403 | `DictTypePolicy`、`DictItemPolicy` |
| 删除内置类型、内置字典项（超管也一样） | 403 | `DictTypePolicy::delete()`、`DictItemPolicy::delete()` |
| 内置类型改 `code`、内置字典项改 `value` | 422（`code` / `value`） | `UpdateDictType`、`UpdateDictItem` |
| `code` 重复、同一类型内 `value` 重复 | 422（`code` / `value`） | 表单校验；并发时 `UniqueConstraint::save()` 捕获唯一索引冲突（新增、修改都是） |

删除类型时连同其字典项一起删除（`DeleteDictType`，外键 `cascadeOnDelete` 兜底）：内置字典项只会出现在内置类型下，不会因此绕过保护。

## 系统参数

接口见设计文档 §6.7（`/system/settings`、`/system/setting-groups/{group}`、`/settings/public`）。只放管理员在运行时调整的业务参数；部署级的配置留在 config 与环境变量。

- 声明（扩展点 `SettingRegistry`，单例）：本模块的分组在 `resources/settings.php`（`site`：站点名称、登录页提示，公开；`auth`：登录限流次数；`attachment`：上传的类型白名单与大小上限）。
  业务模块同样注册自己的分组（一个分组只属于一个模块，键为 `<分组>.<参数名>`）：

  ```php
  // resources/settings.php，由 Provider 的 boot() 中 $settings->register(require __DIR__.'/resources/settings.php') 注册
  return [
      'device' => [
          'title' => 'device.setting.title',
          'order' => 100,
          'settings' => [
              'heartbeat_interval' => ['type' => 'int', 'name' => 'device.setting.heartbeatInterval', 'default' => 60, 'rules' => ['min:10']],
              'paused' => ['type' => 'bool', 'name' => 'device.setting.paused', 'default' => false],
          ],
      ],
  ];
  ```

  `type` 为 `SettingType`；默认值用 `default`（值）或 `config`（config 键，读取时取当前值）二选一；`public` 表示出现在免登录的公开参数接口中；
  `rules` 追加在类型规则之后；`dict`（只用于 `string`、`int`）引用一个字典类型：校验自动追加 `InDict`，管理接口输出 `dict`，前端据此渲染下拉
  （引用的字典不存在时任何值都校验失败）。可选值一律用字典表达，没有静态的选项列表。
- 读取：`resolve(Settings::class)->integer('device.heartbeat_interval')`（或注入 `Settings`）。库中有管理员改过的值（类型一致）就用它，否则用默认值；
  未声明的键抛出 `InvalidArgumentException`。整体缓存（带版本号的键 `core:settings:<版本>`，经 `Cache::memo()`），写入后由 `Observers/SettingObserver` 在事务提交后换新版本。
- `settings.value` 为 null 表示没有改过；修改接口提交 null（或空字符串）即恢复默认值。
- 同步 `php artisan core:sync-settings`：缺的参数新增（`value` 为 null）；结构性字段 `type`、`group`、`name`、`remark`、`is_public` 以声明为准，`value` 不动
  （类型改了、旧值不再符合时置为 null）；从声明中移除的参数取消 `is_builtin` 与 `is_public`，保留值，不删除（废弃的参数留在库中无害，不提供清理命令）。
- 接口只能修改已声明参数的值，不能新增、删除（`SettingPolicy`）；未声明的键或分组 404，值不符合类型或规则 422。
- 登录与两步验证的限流次数读取 `auth.login_rate_limit`（`SystemServiceProvider::loginRateLimit()`，每次请求时读取），默认值来自 `config('core.system.auth.login_rate_limit')`。

## 枚举选项

`Services/EnumRegistry`（`#[Singleton]`）是可下发给前端的枚举的**显式白名单**：`/options/enums?names=...` 只下发登记过的名称（未登记 422），
label 为 `HasLabel::label()`，按 `Accept-Language` 翻译；枚举另外实现了 `HasColor`（`App\Support\Enums`）时每个选项再输出 `color`（登记时校验，规则同字典项的 color）。本模块登记 `status`、`permission_type`、`setting_type`、`export_status`、`export_format`；Monitor 登记 `login_event`、`login_failure_reason`；
Message 登记 `notification_level`、`announcement_status`、`announcement_target`（各自的 `resources/enums.php`）。
业务模块同样在 `resources/enums.php` 中声明（名称以模块名开头），在 Provider 的 `boot()` 中注册：

```php
// resources/enums.php
return ['content.article_status' => ArticleStatus::class];

public function boot(EnumRegistry $enums): void
{
    $enums->register(require __DIR__.'/resources/enums.php');
}
```

## 附件

接口见设计文档 §6.8（`/attachments`、`/system/attachments`）。附件是“上传的文件 + 一条记录”，由业务表单提交附件 id 引用。

- 存储：`config('core.system.attachment.disk')`（默认私有的 `local` 盘），路径 `attachments/<年>/<月>/<日>/<40 位随机串>.<扩展名>`（`hashName()`，扩展名由服务端探测的 MIME 推断），
  不使用客户端的文件名；不按 sha256 去重。下载、预览只经过入口签发的签名临时 URL（过期时间对齐到 `core.system.attachment.url_ttl_minutes` 分钟的窗口，头像为 `avatar_url_ttl`），本模块不签发 URL；
  下载的 ASCII 回退文件名见 `Attachment::asciiFileName()`。跨记录复用文件应复制附件，不能引用别处已绑定的附件。
- 上传：`'file' => ['required', new AllowedUpload]`——白名单 `attachment.allowed_extensions` 与上限 `attachment.max_size`（KB）是本模块 `resources/settings.php`
  的系统参数，内容探测的扩展名（`mimes`）与客户端扩展名（`extensions`）都必须在白名单中。PHP 的 `upload_max_filesize`、`post_max_size` 须不小于上限。
  上传只需登录（`AttachmentPolicy::create()`），由入口限流。
- 业务模块接入（以文章的附件为例）：

  ```php
  // 模型
  class Article extends Model
  {
      use HasAttachments;
  }

  // Form Request：只能用自己上传的未绑定附件，或这篇文章已有的附件
  'files' => ['array'],
  'files.*' => ['integer', new OwnedAttachment($this->user(), record: $this->route('article'), collection: 'files')],

  // Action：保存文章后绑定（被移除的附件连同文件删除）
  resolve(SyncAttachments::class)->handle($article, 'files', $validated['files'] ?? [], 'files');
  ```

  已绑定的附件，访问权委托给记录的 Policy（`can('view', $article)`），业务模块不需要为附件写授权代码；删除文章时它的附件一并删除。
  目前 `attachable_id` 是整数列，绑定对象须为自增整数主键的模型。
- 删除：记录删除后（事务提交后）删除文件，回滚时文件保留；附件管理中已绑定的附件不能删除（403）。
- 孤儿清理：`model:prune` 删除未绑定、创建于 `core.system.attachment.orphan_ttl_hours` 小时之前的附件（`routes/console.php` 每天执行）。
- 头像：`User` 在 `avatar` 用途上绑定的附件，本人在个人资料中提交 `avatar_id` 设置（`OwnedAttachment`，限图片），`null` 去掉；用户管理不能设置他人头像。

| 规则 | 结果 | 在哪 |
|---|---|---|
| 访问未绑定且不是自己上传的附件；访问已绑定附件但看不了所属记录（附件管理员在数据范围内除外） | 403 | `AttachmentPolicy::view()` |
| 附件管理中删除已绑定的附件（超管也一样）；没有 `system.attachment.delete`；超出数据范围 | 403 | `AttachmentPolicy::delete()` |
| 上传的类型不在白名单、超过上限 | 422（`file`） | `AllowedUpload` |
| 引用别人的、已绑定在别处的、不存在的附件；MIME 不符合用途 | 422（对应字段） | `OwnedAttachment`；并发时 `SyncAttachments` 加锁复核 |

## 异步导出

模块接入（设计文档 §5.5、§6.10）：

1. 把列表的查询放进本模块的 Query 类（`builder(User $actor, array $parameters): QueryBuilder`，如 `UserList`），列表控制器调用它；
2. 实现 `Contracts/Exporter`，`query()` 调用同一个 Query 类（同一份筛选白名单与数据范围），在模块的 `resources/exporters.php` 中
   `return ['content.article' => ArticleExporter::class];`，由 Provider 的 `boot()` 中 `$exporters->register(require __DIR__.'/resources/exporters.php')` 注册；
3. 在 `resources/menus.php` 的列表菜单下加按钮 `export`（权限码 `<菜单>.export`）；
4. 入口加 `POST /<列表路径>/export`：请求继承列表请求（去掉 `page`、`pageSize`，加 `format`），`#[Authorize('<菜单>.export')]`，
   经过 `admin-exports` 限流，调用 `CreateExport::handle($actor, '<名称>', $format, $request->listParameters())`。

“我的导出”（`/exports`）、文件下载（附件的签名地址）、完成通知、到期清理都是统一的，模块不用写。

| 规则 | 结果 | 在哪 |
|---|---|---|
| 没有导出权限码 | 403 | 导出入口的 `#[Authorize]` |
| 筛选、排序不在列表的白名单 / 格式不对 | 422 | 列表请求、Query 类（`CreateExport` 同步构造一次） |
| 同一用户排队与进行中的导出超过 `core.system.export.max_active` | 422（`message`） | `CreateExport`（锁用户行） |
| 查看、下载、删除他人的导出（超管也一样） | 403 | `ExportPolicy`、`AttachmentPolicy`（`PrivateAttachable`） |
| 执行时发起人已停用或失去权限码 | 导出失败，不写出数据 | `GenerateExport` |

## 依赖 Core 的能力

无。枚举接口 `HasLabel` / `HasColor` 在 `App\Support\Enums`（与业务无关的通用契约）。

## 配置

`config/permission.php`：模型指向本模块的 `Permission`、`Role`；`register_permission_check_method = false`；`register_octane_reset_listener = true`。

`config/core.php` 的 `auth`：`login_rate_limit`（系统参数 `auth.login_rate_limit` 的默认值）、`frontend_url` 与 `reset_password_path`（重置密码的前端页面）。

`config/core.php` 的 `attachments`：`disk`（存储盘，私有盘）、`url_ttl`、`avatar_url_ttl`（签名地址与头像地址的时间窗口，分钟；过期时间对齐到窗口，有效期为 1～2 个窗口）、`orphan_ttl`（未绑定附件的保留小时数）。

`config/core.php` 的 `exports`：`ttl`（保留小时数）、`max_active`（每人同时进行的上限）、`chunk_size`（每次读取的行数）、`timeout`（队列任务超时，须小于 `retry_after`）、`link`（完成通知的链接）；发起导出的限流在 `apps.admin.export_rate_limit`。

## 生成命令

```bash
php artisan core:make-model <名称> --module=System --core       # 模型、迁移、工厂、Policy，登记多态别名
php artisan core:make-action <名称> --module=System --core      # 写操作
php artisan core:make-query <名称> --module=System --core       # 复杂查询、统计
php artisan core:make-enum <名称> --module=System --core        # 枚举（实现 HasLabel）
php artisan core:make-contract <名称> --module=System --core    # 向受限入口开放的契约
php artisan core:make-controller <名称> --module=System --core --model=<模型>   # 后台接口
```
