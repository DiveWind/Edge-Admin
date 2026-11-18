# System

内核模块，目录 `app/Core/System`。结构与规则见设计文档 §4.3、§5。

## 职责

- 后台用户（`Models/User`，多态别名 `user`）与账号状态（`Enums/Status`：启用 / 停用，用户、角色、菜单节点共用）；
- 认证（设计文档 §7）：Fortify 的登录校验、修改资料、修改密码、重置密码使用本模块的 Action；登录与两步验证的限流器；
  找回密码邮件中的前端链接（均在 `SystemServiceProvider` 中配置）；
- 停用账号、修改密码时让已登录的会话与 token 失效；
- 角色与菜单权限（设计文档 §8.1、§8.2）：`Models/Role`、`Models/Permission`（继承 spatie 的模型，菜单树建在 `permissions` 表上，
  节点类型见 `Enums/PermissionType`）、菜单与权限节点的扩展点 `MenuRegistry`、同步命令 `core:sync-permissions`、
  有效权限码 `PermissionRegistry` 与 `Gate::before`。

不负责：认证接口的路由与响应格式（Fortify 的路由挂在后台入口下，见 `config/fortify.php` 与 `app/Http/Admin`）。
用户、角色、菜单的管理（Policy、Action、接口）、数据权限、字典、参数、附件等在后续接入。

## 对外提供

| 类 | 用途 |
|---|---|
| `Actions/AuthenticateUser` | 用户名 + 密码校验；停用账号抛出 422（`username`）。由 `Fortify::authenticateUsing()` 调用 |
| `Actions/UpdateUserProfileInformation` | 修改姓名、邮箱、头像（Fortify：`PUT /user/profile-information`） |
| `Actions/UpdateUserPassword` | 修改密码，其他会话与全部 token 失效（Fortify：`PUT /user/password`） |
| `Actions/ResetUserPassword` | 重置密码，全部会话与 token 失效（Fortify：`POST /reset-password`） |
| `Actions/DisableUser` | 停用用户：不能再登录，已登录的会话与 token 立即失效（供用户管理调用） |
| `Actions/RevokeUserSessions` | 删除用户的会话（database 驱动，按 `sessions.user_id`，可保留一个）与全部 token |
| `Actions/SyncPermissions` | 把声明的节点同步到 `permissions` 表（`core:sync-permissions` 调用，规则见下文） |
| `Contracts/MenuRegistry` | 扩展点：各模块在 Provider 的 `boot()` 中注册菜单与权限节点（下文） |
| `Services/PermissionRegistry` | 有效权限码与菜单树（`#[Scoped]`），`Gate::before`、`/auth/codes`、`/menu/all` 共用 |
| `Enums/Status` | 通用的启用 / 停用状态，用户、角色、菜单节点共用 |
| `Models/User::isSuperAdmin()` | 是否拥有启用的 `super-admin` 角色（`Role::SUPER_ADMIN`）；`enabledRoles()` 为启用的角色 |

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
注册时校验结构，声明有误直接抛出 `InvalidArgumentException`：name 小写点分且全局唯一；type 合法且层级合法
（catalog / menu / embedded / link 在根或 catalog 下，button 在 menu 下）；路由节点的 path 以 `/` 开头且互不相同、按钮没有 path；
menu 必须有 component、其他类型不能有；embedded 必须有 `meta.iframeSrc`、link 必须有 `meta.link`；meta 只能有 title、icon、order 与白名单键
（`Permission::META_KEYS`）。标题是前端的 i18n key，后端不翻译。

### 同步：`php artisan core:sync-permissions`

由 `DatabaseSeeder` 调用，部署时在 `optimize` 之后执行（设计文档 §11.4）。幂等，输出新增、修正、收编、转为自定义的节点：

1. 声明中有、数据库中没有的节点：新增并标记 `is_builtin`（上级按 name 查找）；已有同名的自定义节点：收编（标记 `is_builtin` 并按第 2 条修正）；
2. 已有节点只修正结构性字段 `type`、`path`、`component`、`meta.link`、`meta.iframeSrc`；`title`、`icon`、`sort`、`status`、`parent_id`
   与 meta 的其他键以数据库为准，管理员的调整不被覆盖；
3. 从声明中移除的内置节点：取消 `is_builtin`，转为自定义节点并保留授权；不删除任何节点；
4. 确保 `super-admin` 角色存在；
5. 清除 spatie 的权限缓存。

### 判定：`PermissionRegistry` 与 `Gate::before`

- 节点从 spatie 的 `PermissionRegistrar` 缓存读取，不额外查库；`#[Scoped]`，每个请求内复用计算结果，spatie 的缓存被清除后重新计算；
- 生效节点：节点本身及全部祖先都启用（停用向子孙级联，超管也受约束）；
- 有效权限码：普通用户要求节点本身及全部祖先都生效，且都授予了用户的**启用**角色；超管为全部生效节点；
- `Gate::before`（`SystemServiceProvider`）：ability 是已登记的权限码 → 按有效权限码返回 true / false；其他 ability → null，交给 Policy
  （超管同样受 Policy 中记录级规则的约束）。spatie 自带的 Gate 检查已关闭（`config/permission.php`）；
- `menuTreeFor()`：`/menu/all` 的节点树（不含按钮，没有可见子节点的目录剔除，同级按 sort、id 排序），输出格式由后台入口的 `MenuRouteResource` 负责。

判定一律走 `can()` / Gate / Policy；spatie 的判定 API（`hasRole()`、`hasPermissionTo()` 等）与 `role:` / `permission:` 中间件被 PHPStan 与 `tests/Arch` 禁止。

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
