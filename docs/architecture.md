# Edge Admin 架构与接口契约

Edge Admin 是一个 RBAC 中后台脚手架：后端 Laravel（`server/`），前端 Ant Design Pro（`web/`）。
两端**只通过本文档描述的 REST 契约通信**，互不引用对方代码，可独立开发、独立部署。

## 1. 仓库布局

```
.
├── server/           # Laravel 应用（纯 API，不含 Blade / Vite 前端资源；独立 composer.json / lockfile）
│   └── app/ bootstrap/ config/ database/ lang/ routes/ tests/ ...
├── web/              # Ant Design Pro 前端工程（独立 package.json / lockfile）
├── docs/             # 项目文档（本文件为契约；其余见 docs/README.md）
└── .github/workflows # CI：后端 pint + phpstan + pest（working-directory: server），前端 lint + tsc + build
```

| 项 | 后端 | 前端 |
|---|---|---|
| 开发端口 | `cd server && php artisan serve` → `127.0.0.1:8000` | `cd web && npm run dev` → `localhost:8001`，`/api` 代理到 8000 |
| 包管理 | Composer | npm（`package-lock.json`） |
| 代码规范 | Pint + Larastan + Pest | Biome + tsc + `npx antd lint ./src` |

## 2. 通用约定

- 前缀 `/api`，JSON，字段 **snake_case**，时间 ISO 8601。
- 认证：Laravel Sanctum **API Token**，`Authorization: Bearer <token>`。
- 成功响应遵循 Laravel API Resource 标准：
  - 单条：`{ "data": { ... } }`
  - 分页列表：`{ "data": [ ... ], "links": { ... }, "meta": { "current_page", "per_page", "total", ... } }`
  - 非分页列表（树、options）：`{ "data": [ ... ] }`
  - 无内容的写操作：`204 No Content`；创建：`201`
- 失败响应遵循 Laravel 标准：`{ "message": "...", "errors"?: { "field": ["..."] } }`
  - `401` 未登录 / token 失效，`403` 无权限，`404`（含记录不存在，不暴露模型名），`405`，`422` 校验失败，
    `429` 限流（带 `Retry-After` 响应头）
  - `message` 为已本地化的提示，前端可直接展示
  - 违反**记录级业务规则**（不能删除自己、受保护对象、有子部门 / 用户 / 文章时不可删、已发布公告不可编辑、
    数据权限之外的记录）返回 `403` + `message`；**字段级约束**（如上级部门不能是自己或下级、超级管理员角色不可改码 / 禁用、
    id=1 用户不可禁用）返回 `422` + `errors`
- 列表查询参数：`page`、`per_page`（默认 15，上限 100，超出返回 422）、`sort`（如 `-created_at`、`sort`，仅允许白名单字段，
  非白名单返回 422；各列表白名单见 5.7）、
  其余为过滤字段（下表 "过滤" 列）。字符串过滤为模糊匹配，`status`/`*_id` 为精确匹配，
  时间范围用 `created_from` / `created_to`（日期，见下方"时间"）。
- 状态字段 `status` 统一为字符串枚举，通用启停为 `active` / `disabled`。
- **多语言**：请求头 `Accept-Language` 决定接口文案语言（`message`、`errors`、操作日志摘要），支持 `zh-CN` / `zh_CN` / `zh`
  → 中文，`en-US` / `en` → 英文；未携带或不支持时使用后端 `APP_LOCALE`（默认 `zh_CN`）。响应头 `Content-Language`
  返回实际语言（`zh-CN` / `en`）。**前端应按当前界面语言显式发送该请求头**（浏览器默认的 Accept-Language 可能与界面语言不一致）。
  字典项 `label`、系统参数、权限点 / 菜单的 `name` 等**数据**不做多语言，菜单与权限点名称由前端按 `code` 做 i18n 展示（`name` 为中文兜底）。
- **限流**：全部 `/api/*` 每分钟 120 次（`EDGE_API_RATE_LIMIT`，已登录按用户、未登录按 IP），登录接口另有独立限流（见 5.1）。
- **时间**：输出的时间字段一律为带时区偏移的 ISO 8601（如 `2026-01-01T08:00:00+08:00`，时区由 `APP_TIMEZONE` 决定，默认 `Asia/Shanghai`）。
  输入的日期时间（表单字段、`created_from` / `created_to` 等过滤参数）：
  - **推荐发送带偏移的 ISO 8601**（如 `2026-11-16T10:30:00+00:00`、`2026-11-16T10:30:00Z`），后端按偏移换算到 `APP_TIMEZONE` 后保存 / 比较，
    返回 `2026-11-16T18:30:00+08:00`；前端日期时间控件统一这样提交，与浏览器所在时区无关；
  - 不带偏移的值（`2026-11-16 18:30:00`、`2026-11-16T18:30:00`）按 `APP_TIMEZONE` 解释；
  - 纯日期（`date` 字段、`created_from` / `created_to`）是 `APP_TIMEZONE` 下的日历日；传带偏移的时间时先换算再取日期。
- 资源对象除本文列出的字段外，通常还带 `*_id` 外键、`created_at`、`updated_at` 等字段（只增不减，前端可忽略）。

## 3. 权限模型

### 3.1 功能权限

- 权限点是**代码里声明的**（`config/permissions.php`，树形：模块 → 菜单 → 操作），
  通过 `php artisan permission:sync` 同步到 `permissions` 表；界面只读展示，并用于给角色授权。
  新增业务功能 = 在配置里加权限点 + sync + 路由上挂 `can:` 中间件。
- 权限码格式 `模块.资源.操作`，如 `system.user.view`；**必须包含 `.`**（不含 `.` 的 ability 视为 Policy 方法名）。
  模块、菜单节点本身也有权限码（如 `system`、`system.user`，`type=menu`），可分配给角色用于菜单展示。
- 后端用 Laravel 原生 Gate：`Gate::before` 中判定；路由用官方 `can:<code>` 中间件。
  记录级规则（数据权限、受保护对象等）由 Policy 判定，超级管理员同样受其约束（例如不能删除自己）。
- 角色 `code = super-admin` 为超级管理员，拥有全部权限且不可删除/禁用/改码；`id=1` 的用户同理受保护。
- 被禁用的角色不生效；被禁用的用户无法登录，已有 token 立即失效。

### 3.2 数据权限

角色字段 `data_scope`：

| 值 | 含义 |
|---|---|
| `all` | 全部数据 |
| `custom` | 自定义部门（`department_role` 关联表） |
| `department_and_children` | 本部门及下级部门 |
| `department` | 仅本部门 |
| `self` | 仅本人创建 |

- 用户拥有多个角色时取**并集**（任一角色为 `all` 即全部）。超级管理员恒为 `all`。
- 业务模型 `use HasDataScope`（约定字段 `department_id` + `created_by`，可在模型上覆盖），
  查询用 `Model::query()->visibleTo($user)`；单条记录的读写在 Policy 中用同一套规则判定。
- 创建记录时自动填充 `created_by` 与 `department_id`（取当前用户）。
- 已接入：用户列表（按用户所属部门 / 本人）、文章。

### 3.3 权限点清单

| 模块 | 权限码 |
|---|---|
| 工作台 | `dashboard.view` |
| 用户 | `system.user.view` `.create` `.update` `.delete` `.reset-password` |
| 角色 | `system.role.view` `.create` `.update` `.delete` |
| 权限 | `system.permission.view` |
| 部门 | `system.department.view` `.create` `.update` `.delete` |
| 岗位 | `system.position.view` `.create` `.update` `.delete` |
| 登录日志 | `monitor.login-log.view` `.delete` |
| 操作日志 | `monitor.operation-log.view` `.delete` |
| 定时任务 | `monitor.scheduled-task.view` `.run` |
| 失败队列 | `monitor.failed-job.view` `.retry` `.delete` |
| 公告 | `message.announcement.view` `.create` `.update` `.delete` `.publish` |
| 文章分类 | `content.category.view` `.create` `.update` `.delete` |
| 文章 | `content.article.view` `.create` `.update` `.delete` |
| 数据字典（类型与字典项共用） | `system.dict.view` `.create` `.update` `.delete` |
| 系统参数 | `system.setting.view` `.create` `.update` `.delete` |
| 附件 | `system.attachment.view` `.delete` |
| 在线会话 | `monitor.online-session.view` `.delete`（强制下线） |
| 系统信息 | `monitor.system-info.view` |

个人中心、我的通知、`/api/options/*`、文件上传只要求登录；`GET /api/options/settings` 无需登录。
对应的菜单级权限码：`system.dict`、`system.setting`、`system.attachment`、`monitor.online-session`、`monitor.system-info`（`type=menu`）。

## 4. 数据表

| 表 | 关键字段 |
|---|---|
| `users` | username(唯一) name email? phone? avatar? department_id? status password last_login_at last_login_ip + 软删除 |
| `departments` | parent_id? name leader_id? sort status |
| `positions` | name code(唯一) sort status remark? |
| `roles` | name code(唯一) data_scope sort status remark? |
| `permissions` | parent_id? name code(唯一) type(`menu`/`action`) sort |
| `role_user` `position_user` `permission_role` `department_role` | 关联表 |
| `login_logs` | user_id? username ip user_agent status(`success`/`failed`) message? created_at |
| `operation_logs` | user_id? method path route_name? summary? ip user_agent status_code duration_ms payload(json，脱敏) created_at |
| `announcements` | title content type(`notice`/`announcement`) status(`draft`/`published`) published_at? created_by |
| `notifications` | Laravel 标准通知表 |
| `scheduled_task_logs` | task status(`running`/`success`/`failed`) output? duration_ms? started_at finished_at? |
| `jobs` `failed_jobs` `job_batches` `cache` | Laravel 标准表 |
| `personal_access_tokens` | Sanctum 标准表 + ip? user_agent?（登录签发时写入；未过期的 token 即"在线会话"） |
| `article_categories` | name sort status |
| `articles` | category_id title summary? cover? content status(`draft`/`published`) source(字典 `article_source`，默认 `original`) published_at? department_id? created_by + 软删除 |
| `dict_types` | name code(唯一) status remark? |
| `dict_items` | dict_type_id label value(同一类型下唯一) color? sort status remark? |
| `settings` | group key(唯一) name value(文本存储，按 type 转换) type(`string`/`number`/`boolean`/`json`) is_public is_system remark? |
| `attachments` | disk path name(原始文件名) mime size(字节) uploaded_by? |

## 5. 接口清单

除登录外全部需要 `auth:sanctum`。"权限" 列为空表示仅需登录。

### 5.1 认证与个人中心

| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/api/auth/login` | `{username, password}` → `{data:{token, token_type:"Bearer", expires_at}}`；限流：按 用户名+IP **只累计失败**（密码错误、账号禁用），每分钟允许的失败次数取系统参数 `security.login_max_attempts`（默认 5），超出后返回 429（带 `Retry-After`，`message` 提示剩余秒数），**登录成功后计数清零**；写登录日志，token 记录 ip / user_agent。账号或密码错误、账号被禁用均返回 `422`，错误在 `errors.username` |
| POST | `/api/auth/logout` | 吊销当前 token，204 |
| GET | `/api/auth/me` | 见下方 CurrentUser |
| PUT | `/api/auth/profile` | `{name, email?, phone?, avatar?}` → CurrentUser |
| PUT | `/api/auth/password` | `{current_password, password, password_confirmation}` → 204（同时吊销该用户其他 token） |

CurrentUser：

```json
{ "data": {
  "id": 1, "username": "admin", "name": "超级管理员", "email": null, "phone": null, "avatar": null,
  "status": "active", "is_super_admin": true,
  "department": { "id": 1, "name": "总公司" },
  "roles": [{ "id": 1, "name": "超级管理员", "code": "super-admin" }],
  "positions": [{ "id": 1, "name": "CEO", "code": "ceo" }],
  "permissions": ["dashboard.view", "system.user.view"],
  "data_scope": "all",
  "unread_notifications_count": 3,
  "last_login_at": "2026-01-01T00:00:00+08:00"
} }
```

`permissions` 为该用户实际拥有的权限码全集（含模块 / 菜单级权限码；超级管理员返回所有权限码）。
`data_scope` 为展示值：多角色时取范围最大的一个（实际可见范围为各角色的并集）。

### 5.2 系统管理

| 方法 | 路径 | 权限 | 过滤 / 说明 |
|---|---|---|---|
| GET | `/api/users` | `system.user.view` | username name phone status department_id（含下级部门） role_id；受数据权限约束 |
| POST | `/api/users` | `.create` | username name password email? phone? avatar? department_id? status role_ids[] position_ids[]（`role_ids`/`position_ids` 必须传，可为空数组；仅超级管理员可分配 super-admin 角色） |
| GET | `/api/users/{id}` | `.view` | 含 department roles positions |
| PUT | `/api/users/{id}` | `.update` | 同创建，无 password |
| DELETE | `/api/users/{id}` | `.delete` | 不可删自己与 id=1（403）；软删除并吊销其 token |
| PATCH | `/api/users/{id}/status` | `.update` | `{status}` → `{data: User}`；受数据权限约束；不能禁用自己（403）；id=1 不可禁用（422 `errors.status`）；禁用时吊销该用户全部 token |
| PUT | `/api/users/{id}/password` | `.reset-password` | `{password, password_confirmation}` → 204（吊销该用户全部 token） |
| GET | `/api/roles` | `system.role.view` | name code status；项含 `users_count`、`is_super_admin` |
| POST/PUT | `/api/roles[/{id}]` | `.create`/`.update` | name code data_scope sort status remark? permission_ids[] department_ids[]（仅 custom 时生效） |
| GET | `/api/roles/{id}` | `.view` | 含 permission_ids department_ids |
| DELETE | `/api/roles/{id}` | `.delete` | 已分配用户的角色不可删 |
| GET | `/api/permissions` | `system.permission.view` | 树 `[{id,parent_id,name,code,type,sort,children:[]}]` |
| GET | `/api/departments` | `system.department.view` | 树（不分页），过滤 name status；节点含 leader{id,name} |
| POST/PUT/DELETE | `/api/departments[/{id}]` | 对应权限 | parent_id? name leader_id? sort status；有子部门或用户时不可删；不可把自己设为自己的下级 |
| GET/POST/PUT/DELETE | `/api/positions[/{id}]` | `system.position.*` | name code status；有用户时不可删 |
| GET | `/api/dict-types` | `system.dict.view` | 过滤 name code status；项含 `items_count` |
| POST/PUT | `/api/dict-types[/{id}]` | `system.dict.create`/`.update` | name code(字母数字 `-` `_`，唯一) status remark? |
| GET/DELETE | `/api/dict-types/{id}` | `.view`/`.delete` | **存在字典项时不可删（403），需先删除字典项**（不级联） |
| GET | `/api/dict-items` | `system.dict.view` | **`dict_type_id` 必填**（缺失 422），过滤 label status；默认按 `sort` 升序 |
| POST/PUT | `/api/dict-items[/{id}]` | `system.dict.create`/`.update` | dict_type_id label value color? sort? status remark?；同一类型下 value 唯一（422 `errors.value`） |
| GET/DELETE | `/api/dict-items/{id}` | `.view`/`.delete` | 204 |
| GET | `/api/settings` | `system.setting.view` | 过滤 group(精确) key name；项见下方 Setting |
| POST/PUT | `/api/settings[/{id}]` | `system.setting.create`/`.update` | group key(小写字母数字下划线，以 `.` 分段，唯一) name type value? is_public? remark?；value 按 type 校验：number 数字、boolean 布尔、json 为对象 / 数组或合法 JSON 字符串、string 字符串；**内置参数（`is_system`）不可改 key / type（422）** |
| GET/DELETE | `/api/settings/{id}` | `.view`/`.delete` | 内置参数不可删（403） |
| GET | `/api/attachments` | `system.attachment.view` | 过滤 name mime(模糊，如 `image/`、`pdf`) uploaded_by created_from/to；项见下方 Attachment，含 `uploader{id,name}` |
| DELETE | `/api/attachments/{id}` | `system.attachment.delete` | 同时删除物理文件，204（引用该 URL 的头像 / 封面等不会自动清空） |

Setting：

```json
{ "id": 1, "group": "site", "key": "site.name", "name": "站点名称", "type": "string",
  "value": "Edge Admin", "is_public": true, "is_system": true, "remark": null, "created_at": "", "updated_at": "" }
```

`value` 按 type 输出为 JSON 字符串 / 数字 / 布尔 / 对象或数组（可为 `null`）。

Attachment：

```json
{ "id": 1, "name": "报告.pdf", "url": "http://.../storage/uploads/2026/01/xxx.pdf", "path": "uploads/2026/01/xxx.pdf",
  "disk": "public", "mime": "application/pdf", "size": 20480, "uploaded_by": 1,
  "uploader": { "id": 1, "name": "超级管理员" }, "created_at": "" }
```

`uploader` 仅在附件列表中返回（上传人被删除时为软删除前的信息，无上传人时为 `null`）。

### 5.3 监控

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/api/login-logs` | `monitor.login-log.view` | 过滤 username ip status created_from/to |
| DELETE | `/api/login-logs/{id}` 、`/api/login-logs` | `.delete` | 单删 / 清空 |
| GET | `/api/operation-logs[/{id}]` | `monitor.operation-log.view` | 过滤 username method path status_code created_from/to；含 user{id,name}；`payload` 恒为 JSON 对象（无内容时为 `{}`） |
| DELETE | `/api/operation-logs/{id}` 、`/api/operation-logs` | `.delete` | 单删 / 清空 |
| GET | `/api/scheduled-tasks` | `monitor.scheduled-task.view` | 读取 Laravel Schedule：`[{key, command, description, expression, next_run_at, last_log?}]`（不分页）；`key` 为命令的 slug（如 `model-prune`、`sanctum-prune-expired-hours-24`），`last_log` 为最近一条执行日志或 `null` |
| POST | `/api/scheduled-tasks/{key}/run` | `.run` | 立即执行一次（入队），202 `{message}`；key 不存在 404 |
| GET | `/api/scheduled-task-logs` | `.view` | 过滤 task status；项：`{id, task, status, output, duration_ms, started_at, finished_at}`，`task` 为命令（如 `model:prune`） |
| GET | `/api/failed-jobs` | `monitor.failed-job.view` | `[{id, uuid, connection, queue, name, exception(摘要), failed_at}]`（不分页；`id` 与 `uuid` 相同，均为 uuid） |
| POST | `/api/failed-jobs/{uuid}/retry` | `.retry` | 重新入队，204；不存在 404 |
| DELETE | `/api/failed-jobs/{uuid}` | `.delete` | 204；不存在 404 |
| GET | `/api/online-sessions` | `monitor.online-session.view` | 未过期的登录 token，分页；过滤 username ip；项：`{id, user{id,name,username}, ip, user_agent, last_used_at, created_at, expires_at, is_current}`，`is_current` 表示发起本次请求的会话 |
| DELETE | `/api/online-sessions/{id}` | `.delete` | 强制下线（吊销 token），204；不能下线当前会话（403） |
| GET | `/api/system-info` | `monitor.system-info.view` | 见下方 SystemInfo |

SystemInfo（不含主机、账号、密钥等敏感配置；无法获取的指标为 `null`）：

```json
{ "data": {
  "app": { "name": "Edge Admin", "env": "production", "debug": false, "version": "1.0.0", "locale": "zh_CN", "timezone": "Asia/Shanghai" },
  "runtime": { "php_version": "8.3.6", "laravel_version": "13.x", "os": "Linux 6.x", "sapi": "fpm-fcgi",
               "memory_limit": "256M", "upload_max_filesize": "2M", "post_max_size": "8M", "server_time": "2026-01-01T08:00:00+08:00" },
  "drivers": { "database": { "connection": "mysql", "driver": "mysql", "version": "8.4.0" },
               "cache": "redis", "queue": "redis", "session": "array", "filesystem": "local", "upload_disk": "public" },
  "storage": { "disk_total_bytes": 0, "disk_free_bytes": 0, "database_bytes": 0, "attachments_bytes": 0 },
  "metrics": { "users": 0, "online_sessions": 0, "attachments": 0, "failed_jobs": 0 }
} }
```

操作日志由中间件自动记录所有已登录的非 GET 请求；`password*`、`token` 等字段脱敏。
定时任务执行日志由 Schedule 事件监听器写入。

### 5.4 消息

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/api/announcements[/{id}]` | `message.announcement.*` | title content type；过滤 title type status；已发布的不可编辑（403）；项含 status published_at creator{id,name} |
| POST | `/api/announcements/{id}/publish` | `.publish` | 置为 published，并通过队列给所有启用用户发送数据库通知；返回 `{data: Announcement}`；已发布的再次发布 403 |
| GET | `/api/notifications` | | 我的通知，分页，过滤 `read`(0/1)；项：`{id, type, title, content, read_at, created_at}` |
| GET | `/api/notifications/unread-count` | | `{data:{count}}` |
| POST | `/api/notifications/{id}/read` 、`/api/notifications/read-all` | | 204 |
| DELETE | `/api/notifications/{id}` | | 204 |

### 5.5 内容（业务对接示例）

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/api/article-categories[/{id}]` | `content.category.*` | name sort status；有文章时不可删 |
| GET | `/api/articles` | `content.article.view` | 过滤 title category_id status source；受数据权限约束；项含 source category{id,name} author{id,name} department{id,name} |
| POST/PUT | `/api/articles[/{id}]` | `.create`/`.update` | category_id title summary? cover? content status source?；status 变为 published 时写 published_at；`source` 必须是字典 `article_source` 的启用项（`original` 原创 / `reprint` 转载 / `translation` 翻译），新增时不传为 `original`，编辑时不传保持不变 |
| GET/DELETE | `/api/articles/{id}` | `.view`/`.delete` | 单条同样受数据权限约束（越权 403） |

### 5.6 公共

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/api/options/departments` | 启用部门树 `[{id, name, parent_id, children}]` |
| GET | `/api/options/permissions` | 权限树，形状同 `GET /api/permissions`；供角色授权表单使用（无需 `system.permission.view`） |
| GET | `/api/options/roles` `/positions` `/article-categories` `/users` | 启用项 `[{id, name}]`（roles/positions 额外带 code） |
| GET | `/api/options/<资源>`（生成器 `--with-options`） | 代码生成器生成的业务模块选项，形状同上 `[{id, name}]`（有 `status` 时仅启用项）；具体资源在各模块契约中登记 |
| GET | `/api/options/dicts?codes=a,b` | `{data: {"a": [{label, value, color}], "b": [...]}}`：仅启用字典的启用项，按 sort；不存在 / 已禁用的 code 返回 `[]`；`codes` 必填（422）；后端缓存，字典变更即失效 |
| GET | `/api/options/settings` | **无需登录**。所有 `is_public` 参数 `{data: {"site.name": "Edge Admin", ...}}`（值已按 type 转换）；登录页用于站点名称、登录页公告、备案号 |
| POST | `/api/uploads` | multipart `file` → `201 {data: Attachment}`（含原有 `url`、`path`，新增 `id` `name` `mime` `size` 等），同时登记为附件。默认允许 jpg jpeg png gif webp pdf doc docx xls xlsx ppt pptx txt csv zip，≤2MB（`EDGE_UPLOAD_EXTENSIONS` / `EDGE_UPLOAD_MAX_SIZE` 可配，不允许 svg / html），超出 422 |
| GET | `/api/dashboard` | `dashboard.view`；见下 |

Dashboard：

```json
{ "data": {
  "totals": { "users": 0, "roles": 0, "departments": 0, "articles": 0, "today_logins": 0, "today_operations": 0,
              "online_sessions": 0, "attachments": 0 },
  "login_trend": [{ "date": "2026-01-01", "success": 0, "failed": 0 }],
  "articles_by_category": [{ "category": "技术", "count": 0 }],
  "users_by_department": [{ "department": "研发部", "count": 0 }],
  "recent_operations": [{ "id": 1, "user": {"id":1,"name":""}, "method": "POST", "path": "", "summary": "", "status_code": 200, "created_at": "" }]
} }
```

`login_trend` 为最近 14 天（含今天，缺失补 0）。`recent_operations` 为最近 10 条操作日志。

### 5.7 列表排序白名单

未传 `sort` 时使用默认排序；所有排序都会追加 `id` 倒序作为稳定的次级排序。

| 列表 | 允许的 `sort` 字段（可加 `-` 前缀倒序） | 默认 |
|---|---|---|
| users | id username created_at last_login_at | `-id` |
| roles / positions / article-categories | id sort created_at | `sort` |
| login-logs | id created_at | `-id` |
| operation-logs | id created_at duration_ms | `-id` |
| scheduled-task-logs | id started_at duration_ms | `-id` |
| announcements / articles | id created_at published_at | `-id` |
| notifications | created_at | `-created_at` |
| dict-types | id created_at | `-id` |
| dict-items | id sort created_at | `sort` |
| settings | id key created_at | `id` |
| attachments | id size created_at | `-id` |
| online-sessions | id last_used_at created_at | `-id` |

## 6. 初始数据（`cd server && php artisan migrate --seed`）

- 账号：`admin` / `password`（超级管理员）；`editor` / `password`（内容编辑，data_scope=`department_and_children`）；`viewer` / `password`（只读访客，data_scope=`self`）；
  另有 `marketing` / `password`（市场主管，data_scope=`custom`，示例自定义部门）与已禁用账号 `disabled`
- 示例部门树、岗位、文章分类与文章、公告。
- 数据字典：`article_source`（原创 / 转载 / 翻译）、`common_yes_no`（是 / 否）、`user_gender`（男 / 女 / 未知）。
- 内置系统参数（`is_system`）：`site.name` 站点名称（公开）、`site.login_notice` 登录页公告（公开）、`site.icp` 备案号（公开，默认空）、
  `security.login_max_attempts` 登录尝试次数（接入登录限流）、`log.retention_days` 日志保留天数（接入 `model:prune`）。
- 角色权限：只读访客自动拥有全部 `*.view` 与菜单权限码；内容编辑额外拥有 `system.attachment.view`。
