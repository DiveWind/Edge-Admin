# System

内核模块，目录 `app/Core/System`。结构与规则见设计文档 §4.3、§5。

## 职责

- 后台用户（`Models/User`，多态别名 `user`）与账号状态（`Enums/UserStatus`：启用 / 停用）；
- 认证（设计文档 §7）：Fortify 的登录校验、修改资料、修改密码、重置密码使用本模块的 Action；登录与两步验证的限流器；
  找回密码邮件中的前端链接（均在 `SystemServiceProvider` 中配置）；
- 停用账号、修改密码时让已登录的会话与 token 失效。

不负责：认证接口的路由与响应格式（Fortify 的路由挂在后台入口下，见 `config/fortify.php` 与 `app/Http/Admin`）。
角色、菜单权限、字典、参数、附件等在后续接入。

## 对外提供

| 类 | 用途 |
|---|---|
| `Actions/AuthenticateUser` | 用户名 + 密码校验；停用账号抛出 422（`username`）。由 `Fortify::authenticateUsing()` 调用 |
| `Actions/UpdateUserProfileInformation` | 修改姓名、邮箱、头像（Fortify：`PUT /user/profile-information`） |
| `Actions/UpdateUserPassword` | 修改密码，其他会话与全部 token 失效（Fortify：`PUT /user/password`） |
| `Actions/ResetUserPassword` | 重置密码，全部会话与 token 失效（Fortify：`POST /reset-password`） |
| `Actions/DisableUser` | 停用用户：不能再登录，已登录的会话与 token 立即失效（供用户管理调用） |
| `Actions/RevokeUserSessions` | 删除用户的会话（database 驱动，按 `sessions.user_id`，可保留一个）与全部 token |

- 受信任的入口直接读取本模块的模型，写入调用 `Actions`；接口放在各受信任入口的同名目录：`app/Http/Admin/System`。
- 受限的入口只通过 `Contracts` 与 `Data` 访问（`php artisan core:make-contract`）。
- 停用账号的即时检查是多入口共用的中间件 `App\Http\Middleware\EnsureUserIsEnabled`（放在各入口的中间件组中）。
  会话为 database 以外的驱动时，停用后的会话由它在下一次请求时注销；修改密码后的其他会话由 Sanctum 的 `AuthenticateSession` 注销。

## 依赖 Core 的能力

无。`Contracts/HasLabel` 是本模块提供给 Core 与业务模块的枚举接口。

## 配置

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
