# 后端设计文档（目标架构）

> **状态：设计稿，尚未实施。** 本文描述下一阶段的目标架构，是后续改造的依据。
> 现有代码的实际情况见 [architecture.md](architecture.md)、[backend-guide.md](backend-guide.md)。
> 两者冲突时：已实施的行为以现有文档为准，新开发按本文执行。
>
> - 定位为**脚手架（Starter）**，不设品牌名；文中称“本脚手架”。
> - 库版本号核查于 2026-11，落地时以 Packagist / npm 当时的最新稳定版为准。

## 目录

1. [定位](#1-定位)
2. [设计原则](#2-设计原则)
3. [技术栈](#3-技术栈)
4. [仓库与目录结构](#4-仓库与目录结构)
5. [核心与应用：分层和依赖边界](#5-核心与应用分层和依赖边界)
6. [与 Vben 的接口契约](#6-与-vben-的接口契约)
7. [认证](#7-认证)
8. [授权与数据权限](#8-授权与数据权限)
9. [安全规范](#9-安全规范)
10. [编码规范](#10-编码规范)
11. [运行、开发与部署](#11-运行开发与部署)
12. [依赖清单](#12-依赖清单)
13. [自建基础设施清单](#13-自建基础设施清单)
14. [待决事项](#14-待决事项)

---

## 1. 定位

一个 **Laravel + Vben 的中后台脚手架（Starter）**：纯 API 后端，默认适配 [Vben Admin 5](https://github.com/vbenjs/vue-vben-admin)（antdv-next 版），但不绑定任何前端。

- **克隆即拥有全部代码**：与 Laravel 官方 Starter Kit 相同，使用者在此基础上直接开发业务，而不是以“框架包”的形式依赖和升级。
- 提供 RBAC、数据权限、动态菜单、日志、通知、字典、参数、附件、定时任务等后台通用能力。
- 支持一个核心加多个独立入口（多应用），例如后台 API、设备接入 API、开放平台 API。
- **尽量少自建基础设施**：能用 Laravel 官方能力或社区事实标准实现的，一律不自己写；必须自建的部分全部登记在 [§13](#13-自建基础设施清单)。

**不做的事**

- 不渲染页面（没有 Blade 后台、没有 Livewire）；不在后端定义前端的表单、表格、布局。
- 不内置部门、岗位等组织架构。需要时由业务按需加入，数据权限预留了扩展点（见 [§8.4](#84-数据权限)）。

## 2. 设计原则

1. **官方优先，少自建。** Laravel 官方包能做的，不引入第三方；官方没有的，用社区事实标准（spatie）；都没有的才自己写，并登记在 §13。
2. **前后端彻底分离。** 后端只提供数据和规则，界面完全由前端决定。
3. **业务规则只有一份，在核心里。** 无论从后台、设备、命令行还是队列进入，执行的都是同一套规则。
4. **默认拒绝。** 授权、数据权限、多租户隔离一律 fail-closed：缺规则 = 不允许。
5. **规范要能被机器检查。** 能用 Pest 架构测试、PHPStan、Pint、Rector 强制的约定，就不只写在文档里。
6. **选定一种写法就全项目统一，并写进代码生成器。** 一致性比选哪一种更重要。
7. **默认 PHP-FPM，常驻内存安全。** 默认以 PHP-FPM 运行；所有代码都按常驻内存安全来写，可无缝切换到 Octane + FrankenPHP。

## 3. 技术栈

| 层 | 选型 |
|---|---|
| 语言 | PHP 8.5 |
| 框架 | Laravel 13 |
| 运行时 | 默认 PHP-FPM（Nginx 或 Caddy）；可无缝切换到 Laravel Octane + FrankenPHP |
| 认证 | Laravel Fortify（登录全流程）+ Sanctum（后台用会话 Cookie，设备 / App 用 token） |
| 授权 | Gate + Policy + spatie/laravel-permission |
| 数据库 | MySQL / PostgreSQL / SQLite（代码不写方言 SQL） |
| 会话 / 缓存 / 队列 | 开发：database 驱动；生产：Redis |
| 前端（默认适配） | Vben Admin 5 + antdv-next，独立目录 `web/` |
| 开发环境 | Herd、Sail、本机 PHP 均可；验证 FrankenPHP 时用 Sail 或 WSL2 |

## 4. 仓库与目录结构

### 4.1 仓库根目录

```
.
├── server/          Laravel 后端（本文主体）
├── web/             Vben 前端（monorepo，保留 apps/web 与 playground）
├── docs/            项目文档
├── deploy/          Nginx / Caddyfile、Dockerfile、Supervisor 等部署文件
├── .github/         CI、Issue / PR 模板
├── README.md  CLAUDE.md  AGENTS.md  LICENSE  CHANGELOG.md  CONTRIBUTING.md  SECURITY.md
└── .mcp.json        Laravel Boost MCP（php server/artisan boost:mcp）
```

前后端只通过 [architecture.md](architecture.md) 中的接口契约通信，互不引用代码。

### 4.2 `server/` 目录

```
server/
├── app/
│   ├── Core/                         核心：拥有数据库、模型、业务规则
│   │   ├── Contracts/                ← 对受限应用导出的接口
│   │   ├── Data/                     ← 对受限应用导出的只读数据对象（readonly）
│   │   ├── Events/                   ← 对外导出的领域事件
│   │   ├── System/                   用户、角色、菜单权限、字典、参数、附件
│   │   ├── Monitor/                  日志、在线会话、定时任务、系统监控
│   │   ├── Message/                  公告、通知
│   │   └── Content/                  文章（业务对接示范，可整块删除）
│   │
│   ├── Admin/                        应用：后台 API（受信任），给 Vben 用
│   │   ├── System/                   ← 与 Core/System 一一对应
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Monitor/  Message/  Content/
│   │   ├── Http/Middleware/          整个后台应用共用的中间件（响应包装等）
│   │   ├── routes/api.php
│   │   └── AdminServiceProvider.php  注册本应用的路由、中间件组、限流器等
│   │
│   ├── Device/                       应用：设备接入（受限，以后加）
│   │
│   └── Support/                      与业务无关的跨应用基础件
│
├── bootstrap/  config/  database/  lang/  public/  storage/
├── routes/console.php                只放调度定义
├── stubs/                            定制后的官方 stub + 生成器模板
└── tests/
    ├── Core/{System,Monitor,Message,Content}/
    ├── Admin/
    ├── Device/
    └── Arch/                         边界与约定测试
```

### 4.3 核心业务模块内部

每个模块结构相同，按需创建子目录，不建空目录：

```
app/Core/System/
├── Models/            Eloquent 模型
├── Actions/           写操作用例（一个类一个用例，handle() 方法）
├── Policies/          授权规则
├── Queries/           复杂查询、统计指标（只读）
├── Enums/             枚举（实现统一的 HasLabel / HasColor 接口）
├── Events/  Listeners/  Observers/  Jobs/  Notifications/
├── Rules/  Casts/
├── resources/
│   ├── lang/          模块自己的语言包（命名空间 system::）
│   └── views/         邮件、PDF、导出模板（不对外访问）
├── README.md          模块说明：职责、对外提供什么
└── SystemServiceProvider.php
```

需要浏览器直接下载的文件放 `resources/public`，部署时 `vendor:publish` 到 `public/vendor/<模块>`。**`app/` 目录绝不对外访问**；Web 根目录只能是 `public/` 和前端构建产物。

### 4.4 命名约定

- **应用按“给谁用”命名**：后台（`Admin`）、设备（`Device`）、用户端（`Portal`）、开放平台（`Open`）……
- **核心叫 `Core`**，业务模块用业务名（`System`、`Monitor`、`Message`、`Content`）。
- 应用内部的目录与核心模块**同名一一对应**。删除一个业务 = 删除 `Core/<模块>` + 各应用里的同名目录 + 前端对应页面。

### 4.5 偏离默认目录：官方支持与需要的配置

官方文档（Directory Structure）明确：默认结构只是起点，可以按需组织，“只要 Composer 能自动加载，Laravel 几乎不限制任何类的位置”。核心模块与应用采用官方“包开发”（Package Development）的方式注册自己的路由、命令、语言包和视图。

| 默认行为 | 是否受影响 | 处理（均为官方机制） |
|---|---|---|
| Policy 自动推断 | **不受影响** | 框架会把模型命名空间中的 `\Models\` 替换为 `\Policies\` 查找，`App\Core\System\Models\User` 自动对应 `App\Core\System\Policies\UserPolicy` |
| 工厂推断 | **不受影响** | `App\Core\System\Models\Dict` 默认对应 `Database\Factories\Core\System\Models\DictFactory`，`make:model ... -f` 也按此生成；只是目录较深，可选用 `Factory::guessFactoryNamesUsing()` 统一扁平化 |
| 观察者、任务、通知、规则、类型转换、枚举、Action | **不受影响** | 无自动发现机制，放在哪里都可用；观察者用 `#[ObservedBy]` 绑定 |
| 命令自动发现 | 受影响（默认只扫描 `app/Console/Commands`） | 模块 Provider 中 `$this->commands([...])`（包开发方式） |
| 事件监听自动发现 | 受影响（默认只扫描 `app/Listeners`） | `withEvents(discover: [...])` 列出各模块的 `Listeners` 目录，或在模块 Provider 中显式注册 |
| 路由、语言包、视图 | 需注册 | Provider 中 `loadRoutesFrom()`、`loadTranslationsFrom()`、`loadViewsFrom()`，路由缓存照常可用 |
| 迁移 | **不受影响** | 保留在 `database/migrations` |
| 认证模型、权限模型 | 需配置 | `config/auth.php`、`config/permission.php` 指向核心的模型 |
| 多态类型 | **必须第一天配置** | 在 `AppServiceProvider` 中 `Relation::enforceMorphMap()`。否则通知、日志、token、角色关联等表会存入完整类名，以后移动类就要迁移数据（官方文档的提醒） |
| 官方 `make:*` 命令 | 受影响（默认命名空间固定） | 名称一律写以 `App/` 开头的完整路径，如 `make:controller App/Admin/System/Controllers/UserController`；日常使用生成器 |

## 5. 核心与应用：分层和依赖边界

### 5.1 三档访问权限

| 谁 | 读数据 | 写数据 |
|---|---|---|
| **Core** | 随意 | 随意（规则的拥有者） |
| **受信任的应用**（后台） | 直接使用核心模型：查询、路由模型绑定、API Resource 输出 | **必须调用核心的 Action**，禁止直接 `save()` / `update()` / `delete()` / `create()` |
| **受限的应用**（Device 等） | 只能通过 `Core\Contracts` 拿 `Core\Data` | 只能通过 `Core\Contracts` |

理由：后台要用的几乎是核心全部数据，强制走接口等于用接口重写一遍 Eloquent；受限应用只需要两三个能力，且不应知道内部结构。

### 5.2 依赖规则

- 核心不依赖任何应用。应用之间互不依赖。
- 应用里不得定义模型、迁移和业务规则。
- Policy 属于核心，**与调用方无关**：禁止出现“判断是哪个应用在调用”的分支。
- 核心通知应用的唯一方式是事件；应用不被核心直接调用。
- 受限应用可以使用缓存、日志、限流等**临时或无状态**的基础设施，但需要持久保存的业务数据一律交给核心。

### 5.3 各层职责

| 层 | 职责 | 不做 |
|---|---|---|
| Controller（应用） | 授权声明 → 调用 Action / 查询 → 返回 Resource | 业务逻辑、事务 |
| Form Request（应用） | 输入格式校验（必填、长度、类型、白名单字段） | 业务约束 |
| API Resource（应用） | 按该应用的前端格式输出，**逐个列出字段** | 查询 |
| Action（核心） | 写入、事务、业务约束（唯一性、状态流转、引用检查）、触发事件 | 依赖 HTTP 层 |
| Query（核心） | 复杂查询、统计指标（独立类，统一缓存策略） | 写数据 |
| Policy（核心） | 记录级授权、数据权限判定 | 依赖调用方 |
| Contracts / Data（核心） | 受限应用的唯一入口 | 暴露模型 |

所有写入都经过 Action（保证“写只有一个入口”）；单表、无副作用的 Action 由生成器自动生成，不增加手写成本。

### 5.4 强制手段

- **Pest 架构测试**：上述依赖规则；后台应用不得依赖 `DB` 门面；应用内不得出现模型；受限应用不得依赖核心模型。
- **“写必须走 Action”**：Pest 无法检测方法调用，使用 PHPStan 扩展 `spaze/phpstan-disallowed-calls` 禁止后台应用中调用模型写方法（落地前核实版本）。
- **代码审查清单**写入 `CONTRIBUTING.md`。

### 5.5 应用的注册与运行

- 每个应用一个 ServiceProvider（如 `AdminServiceProvider`），在 `bootstrap/providers.php` 中注册，负责本应用的全部入口配置：加载自己的路由文件、URL 前缀（或域名）、中间件组、认证方式、限流器、异常输出格式。新增应用 = 新增一个目录 + 一个 Provider，不改动共享文件。
- `config/core.php` 登记应用清单；进程可按环境变量只加载部分应用，例如 `CORE_APPS=admin,device`。未加载的应用其路由在该进程中不存在。
- 对外开放的应用（Device、开放平台）路由**从第一版起带版本号** `/v1`；后台只服务自家前端，不带版本号。
- 对外开放的应用暴露的资源使用 **ULID**（`HasUlids`），不暴露自增 ID。

## 6. 与 Vben 的接口契约

以下约定只属于**后台应用**；其他应用可以有自己的格式。

### 6.1 响应格式

- **HTTP 状态码照常使用**（200 / 201 / 204 / 401 / 403 / 404 / 419 / 422 / 429 / 500）。
- 响应体统一包装为 Vben 默认格式，由后台应用的中间件 + 响应宏完成，控制器与 Fortify 无感知：

```json
// 成功
{ "code": 0, "data": { ... }, "message": "ok" }
// 分页
{ "code": 0, "data": { "items": [ ... ], "total": 123 }, "message": "ok" }
// 失败（HTTP 422）
{ "code": 422, "data": null, "message": "数据校验失败", "errors": { "username": ["..."] } }
```

### 6.2 认证与菜单相关接口

| 接口 | 提供方 | 说明 |
|---|---|---|
| `GET /sanctum/csrf-cookie` | Sanctum | 登录前获取 CSRF 令牌 |
| `POST /login`、`POST /logout` | Fortify | 开启两步验证时，登录返回“需要两步验证”的标记 |
| `POST /two-factor-challenge` | Fortify | 提交动态码或恢复码完成登录 |
| `POST /forgot-password`、`POST /reset-password` | Fortify | 找回密码 |
| 两步验证的开启、确认、关闭、恢复码 | Fortify | 个人设置中使用 |
| `GET /auth/codes` | 自建 | 当前用户的按钮权限码列表 |
| `GET /user/info` | 自建 | 当前用户信息；前端启动时用它判断是否已登录 |
| `GET /menu/all` | 自建 | 当前用户可见的菜单树，结构与 Vben 路由一致：`path`、`component`、`meta { title, icon, order, ... }` |

Fortify 路由前缀与后台应用一致（`fortify.prefix`）。

**Vben 侧的调整**（都在 `apps/web` 内，约 4 个文件，共享包不改）：请求携带 Cookie 并自动带 CSRF 令牌；去掉 `Authorization` 请求头与刷新令牌逻辑；登录态改为“已登录标记 + 启动时请求 `/user/info`”；路由守卫改为判断用户信息。

### 6.3 列表查询

- 参数：分页、排序、筛选，使用 `spatie/laravel-query-builder`，**允许的筛选和排序字段显式白名单**。
- 返回 `{ items, total }`。

## 7. 认证

### 7.1 方案

| 调用方 | 方式 |
|---|---|
| 后台（Vben） | **Sanctum SPA 模式（会话 Cookie）+ Fortify 全套登录流程** |
| 设备、App、脚本 | **Sanctum token**（Bearer） |
| 开放平台、第三方授权（以后） | Passport（OAuth2） |

- 业务代码统一使用 `auth:sanctum`，不区分请求来自会话还是 token。
- 不使用 JWT：无法立即吊销，“强制下线、禁用立即生效”会变弱，Laravel 也没有官方 JWT 包。
- **部署前提**：后台前端与 API 在同一个主域名下（同域不同路径，或 `admin.example.com` 与 `api.example.com`）。本地开发用 Vite 代理保持同源。

### 7.2 Fortify 提供的能力（直接使用，不自写）

| 功能 | 说明 |
|---|---|
| 登录、登出、登录限流 | 登录字段配置为 `username`；在 `Fortify::authenticateUsing()` 中拒绝已禁用账号；限流次数由系统参数控制 |
| 两步验证 | TOTP 动态码 + 恢复码，开启后需确认才生效 |
| 找回密码、重置密码 | 邮件链接重置 |
| 邮箱验证 | 按需开启 |
| 敏感操作前确认密码 | 如关闭两步验证、修改安全设置 |
| 修改个人资料、修改密码 | 修改密码后让其他设备下线（`Auth::logoutOtherDevices()`） |
| 注册 | **关闭** |
| Passkeys | Fortify 已集成；依赖的 `laravel/passkeys` 仍为 0.x，**暂不开启** |

需要定制响应时，绑定 Fortify 的响应契约类，不改 Fortify 本身。

### 7.3 认证相关的自建部分

- **登录日志**：监听 Laravel 标准认证事件（登录成功、失败、锁定）写入。
- **在线会话与强制下线**：会话使用 `database` 驱动（生产可用 Redis 并另行记录），列表读取会话记录；强制下线即删除会话；设备 / App 的 token 一并展示与吊销。
- **禁用账号立即生效**：中间件检查账号状态，并删除该用户全部会话与 token。
- **设备 / App 的 token 签发接口**（Fortify 不发 token）。
- **指定角色必须开启两步验证**：登录后的中间件检查，未开启则只允许访问两步验证设置。

### 7.4 安全措施

- 会话 Cookie：`HttpOnly`、线上 `Secure`、`SameSite=Lax`；CSRF 由 Sanctum 处理。
- token：设置过期时间；启用 Sanctum `token_prefix` 便于识别泄露；用 abilities 限定权限范围；定时清理过期 token。
- 改密码、禁用账号、强制下线时，吊销对应的会话与 token。

## 8. 授权与数据权限

### 8.1 组件分工

```
请求 → Sanctum：你是谁
     → Gate（spatie 提供权限数据）：你能不能做这类事
     → Policy：你能不能动这一条
     → 控制器
```

- **spatie/laravel-permission** 只负责“角色与权限存在哪、怎么查”，接入 Gate。
- **禁止**使用 spatie 的 `role:`、`permission:` 中间件和 `hasRole()` 判断：只按权限判断，不按角色判断（架构测试强制）。
- 超级管理员用 `Gate::before` 放行。

### 8.2 菜单与权限

- 权限扩展为菜单树：目录 / 菜单 / 按钮三种类型，含路径、组件、图标、排序、显示、状态。
- 内置节点在配置中声明并同步（标记为内置，不可删除、不可改权限码）；管理员在界面上的调整在再次同步时不被覆盖。
- 停用的节点及其子孙对所有人（含超管）失效；为防止自锁，菜单管理相关节点不可停用。

### 8.3 Policy 规范

- **标准方法齐全**：`viewAny`、`view`、`create`、`update`、`delete`、`restore`、`forceDelete`，以及批量的 `deleteAny`、`restoreAny`、`forceDeleteAny`。
- **关联操作单独授权**：如分配角色、挂载分类，各有对应方法。
- 权限码检查放在 Policy 内部；控制器只声明 `#[Authorize('update', 'user')]` 这类标准写法。
- **默认拒绝**：测试强制要求每个模型都有 Policy 且标准方法齐全；每条受保护路由都有授权声明。
- 批量操作：默认检查一次批量方法；需要时逐条检查。

### 8.4 数据权限

- 默认提供两种范围：**全部**、**仅本人**（按 `created_by`）；多角色取并集。
- 职责划分：**角色决定能做什么（功能权限），范围提供者决定能看哪些数据（数据权限）**。多角色取并集只会让可见范围变大，不会变得更细；更细的范围需要新增一个数据维度（即新增提供者）。
- **不内置部门等组织维度**。数据权限通过“范围提供者”接口实现：业务需要按部门、区域、门店等维度控制时，新增一个提供者并在角色上选用即可，不改动脚手架代码。
- 通过模型 trait 提供 `visibleTo()` 查询范围与 `isVisibleTo()` 单条判定，Policy 复用同一规则。
- **所有查询入口**都必须经过数据权限：列表、详情、**下拉选项接口**、导出、统计、全局搜索。
- 禁止不带参数的 `withoutGlobalScopes()`（PHPStan 规则拦截），必须明确列出要去掉的作用域。
- 未来引入多租户时同样 fail-closed：拿不到租户即拒绝，而不是不过滤。

## 9. 安全规范

| 类别 | 规范 |
|---|---|
| 输出 | API Resource 逐个列出字段，禁止整模型 `toArray()` 输出；敏感字段加 `#[Hidden]` |
| 输入 | 写接口拒绝未声明字段（`failOnUnknownFields`）；网址类字段只允许 `http` / `https` |
| 富文本 | **保存时在后端净化**（`symfony/html-sanitizer`，落地前核实版本），去除脚本与危险属性 |
| 上传 | 随机文件名；校验 MIME 与大小；**引用附件时校验归属**（只能使用自己上传或有权访问的附件） |
| 路由参数 | 统一格式约束（数字 ID、ULID、UUID），非法参数直接 404 |
| XSS 纵深防御 | Web 服务器下发 CSP 响应头；前端不直接渲染用户输入的 HTML |
| 依赖 | CI 执行 `composer audit`、`npm audit` |
| 日志 | 操作日志对密码、token 等字段脱敏；每个响应带 `X-Request-Id` |

## 10. 编码规范

### 10.1 Laravel 写法

- 授权：控制器方法上用 `#[Authorize]`；路由文件只描述结构（分组导入、`apiResources`）。
- 模型：`#[Fillable]`、`#[Hidden]`、`#[ObservedBy]` 等 Attribute（工厂、Policy 按默认规则自动推断，不需要时不写 `#[UseFactory]`、`#[UsePolicy]`）；`casts()` 方法；禁止 `$fillable` 等属性写法。
- 命令：`#[Signature]`、`#[Description]`。队列任务：`#[Tries]`、`#[Backoff]` 等，**队列名不写在任务上**，统一用 `Queue::route()` 集中登记。
- 统计类数据用 `Cache::flexible()`；请求内缓存用 `Cache::memo()` 或 `#[Scoped]`。
- 时间：写库前统一转换为应用时区；输出带时区偏移的 ISO 8601。
- 枚举：实现统一的 `HasLabel`（多语言）/ `HasColor`（语义色，如 success / warning）接口，由后端统一下发选项。
- 统计指标、复杂查询做成核心模块里独立的 Query 类。
- 软删除是整套功能：恢复、彻底删除、“已删除”筛选、对应授权，生成器一个参数生成。
- 导出：`simple-excel` + 队列 + 完成通知，标准异步导出。
- 对接第三方 API 时统一使用 Saloon（不作为默认依赖）。

### 10.2 PHP 8.4 / 8.5 特性

| 特性 | 用法 |
|---|---|
| `#[\NoDiscard]` | 权限、数据权限、校验类方法：返回值不用即告警 |
| `clone($obj, [...])` | 只读数据对象的 `withXxx()` 方法 |
| 管道运算符 `\|>` | 串联普通函数、纯函数；不替换已有的 `Str` / 集合链式写法 |
| 属性钩子、不对称可见性 | 用于数据对象、值对象、服务类；**不用于 Eloquent 模型字段** |
| `new X()->method()` | 去掉多余括号 |
| `array_find` / `array_any` / `array_all` / `array_first` / `array_last` | 替代简单循环 |
| `#[\Deprecated]` | 废弃公开方法时使用 |
| `Uri\Rfc3986\Uri` | 网址解析与校验 |

**前提**：落地前确认 Pint、PHPStan / Larastan、Rector、Pest、Scramble 全部支持所用新语法；先由 Rector 批量升级，再人工处理。

### 10.3 常驻内存兼容规则

默认运行在 PHP-FPM 下也必须遵守，保证随时可切换到 Octane + FrankenPHP：

- 禁止用静态属性或单例保存请求相关数据；需要请求级状态时用 `#[Scoped]`、`Cache::memo()`、`Context`。
- 禁止在请求中修改 `config()`。
- 禁止 `exit()`、`die()`。
- 引入第三方包前确认其支持 Octane；有状态的服务登记到 Octane 的 `flush` 配置。
- 以上能自动检查的（静态属性、`exit`）加入架构测试。

### 10.4 代码生成

- `make:module`：在核心生成业务模块，同时在后台应用生成对应的同名目录；底层编排官方 `make:*` 命令与定制 stub。
- `make:app`：新建一个应用（目录、ServiceProvider、路由、中间件组、测试目录、架构测试登记）。
- 生成内容包括：模型、迁移、工厂、Action、Policy（标准方法齐全）、Request、Resource、控制器、权限节点、路由、操作日志文案、**授权测试**、模块 README。
- **新建文件必须使用生成器**，写入 `CLAUDE.md`。

### 10.5 测试

- Pest；`tests/` 目录与 `app/` 对应。
- `tests/Pest.php` 提供统一辅助：以某权限登录、断言无权限返回 403、断言数据权限过滤。
- 每个接口至少覆盖：成功、无权限 403、数据权限之外 403/404、校验失败 422。
- 核心安全模块（认证、权限、数据权限）运行 Pest 变异测试。
- CI 在 Linux + PHP 8.5 下运行全部测试，并在 Octane 下额外运行一遍，确保可无缝切换。

## 11. 运行、开发与部署

### 11.1 开发环境

| 方式 | 说明 |
|---|---|
| Herd（Windows / macOS） | 本机开发的推荐方式，PHP-FPM 模式，与默认生产形态一致 |
| Sail（Docker） | 团队统一环境；也用于验证 FrankenPHP |
| 本机 PHP | `php artisan serve` |

- 队列开发时用 `queue:listen`；Horizon 依赖 `pcntl`，Windows 本机不可用，仅在 Linux 环境使用。
- 前端开发时用 Vite 代理把后台接口转发到后端，保持同源（会话模式的要求）。

### 11.2 部署形态

两种形态，代码完全相同，只是 Web 服务不同：

```
/app/server/        Laravel（public/ 只放 Laravel 自身文件）
/app/web/dist/      Vben 构建产物
```

**默认：Nginx（或 Caddy）+ PHP-FPM**

- 后台接口、`/sanctum/*`、`/storage/*`、`/up` → PHP-FPM；
- 其他路径 → `web/dist` 静态文件，找不到时回退到 `index.html`；
- 带哈希的静态资源长期缓存，`index.html` 不缓存。

**可选：FrankenPHP（Octane worker 模式）**

- 一个进程同时托管 API 与静态文件，Caddy 自动申请 HTTPS 证书；
- 路由分流规则与默认形态相同。

`deploy/` 同时提供两套配置（Nginx + PHP-FPM、Caddyfile + FrankenPHP）。镜像用多阶段构建：Node 构建前端 → Composer 安装后端 → 运行时镜像。构建产物不进 git。

### 11.3 进程

| 进程 | 说明 |
|---|---|
| Web | PHP-FPM，或 Octane（FrankenPHP） |
| 队列 | `queue:work` 或 Horizon（Redis + Linux），由 Supervisor / systemd 管理 |
| 调度 | cron 调用 `schedule:run`，或 `schedule:work` |
| Reverb | 启用实时推送时 |

### 11.4 发布流程

1. 数据库迁移（迁移必须向下兼容）；
2. 切换新代码或新镜像；
3. `php artisan optimize`；
4. PHP-FPM：平滑重载 FPM（刷新 OPcache）；Octane：`php artisan octane:reload`（**必需**，否则仍运行内存中的旧代码）；
5. `php artisan queue:restart`，让队列进程加载新代码。

### 11.5 本地与线上的差异

| 差异 | 保证手段 |
|---|---|
| 线上使用 Octane 时的常驻内存问题 | §10.3 规则 + CI 在 Octane 下测试 |
| 文件名大小写、换行符 | CI 在 Linux 运行；`.gitattributes` 统一 LF |
| Windows 缺失组件（Horizon、`df` 等） | 按环境注册，配置开关隔离 |
| HTTPS、代理、Cookie | `APP_URL` 用 https；线上 `SESSION_SECURE_COOKIE`；配置可信代理与 Sanctum 有状态域名 |
| 上传大小 | PHP 与 Web 服务器的限制一致 |
| PHP 版本与扩展 | `composer.json` 固定平台为 8.5；必要扩展在运行时镜像中安装 |

## 12. 依赖清单

### 12.1 运行时依赖

**Laravel 官方**

| 包 | 用途 |
|---|---|
| laravel/framework 13 | 框架 |
| laravel/fortify 1.43 | **完整使用**：登录、两步验证、找回密码、邮箱验证、确认密码、个人资料 |
| laravel/sanctum | SPA 会话认证 + token |
| laravel/pulse 1.11 | 生产性能监控 |
| laravel/octane 2.25 | 可选：常驻内存运行（FrankenPHP） |
| laravel/horizon 5.52 | 可选：Redis 队列管理（需 pcntl，Linux） |
| laravel/reverb 1.14 | 可选：实时推送 |
| laravel/pennant 1.28 | 可选：功能开关 |

**spatie**

| 包 | 用途 |
|---|---|
| laravel-permission 8.5 | 角色与权限存储 |
| laravel-query-builder 7.3 | 列表筛选、排序、关联加载 |
| laravel-activitylog 5.1 | 数据变更审计、操作日志、登录日志（统一存储，按日志名区分） |
| laravel-backup 10.4 | 数据库与文件备份 |
| laravel-health 1.42 | 健康检查 |
| laravel-schedule-monitor 4.3 | 定时任务执行监控 |
| simple-excel 3.11 | 流式导入导出 |

**其他**

| 包 | 用途 |
|---|---|
| dedoc/scramble 0.13 | OpenAPI 文档（仅本地环境开放） |
| symfony/html-sanitizer | 富文本净化（落地前核实版本） |

### 12.2 开发与 CI

| 工具 | 用途 |
|---|---|
| laravel/pint | 代码风格（含分组导入规则） |
| larastan/larastan + phpstan | 静态分析，level 8 起，逐步提高；`phpstan-deprecation-rules` |
| spaze/phpstan-disallowed-calls | “写必须走 Action”等禁止调用规则（落地前核实） |
| rector/rector + driftingly/rector-laravel | 语法升级、Laravel 升级 |
| pestphp/pest 5 + pest-plugin-mutate | 测试、架构测试、变异测试 |
| laravel/telescope | 本地调试 |
| laravel/boost | AI 辅助开发（MCP、规范） |
| laravel/sail | 开发环境 |
| PCOV | CI 覆盖率 |
| composer audit / npm audit | 依赖安全审计 |

### 12.3 按需（写进规范，不默认安装）

`saloonphp/saloon`（对接第三方 API）、`deptrac/deptrac`（模块变多后的分层可视化）、`spatie/laravel-medialibrary`（附件缩略图）、`laravel-model-states`、`laravel-pdf`、`laravel-webhook-*`、`laravel-multitenancy`、`laravel/passport`（开放平台）、`laravel/mcp`（AI 调用后台能力）。

### 12.4 明确不使用

| 不用 | 原因 |
|---|---|
| JWT（tymon/jwt-auth 等） | 无法立即吊销；Laravel 无官方包 |
| JSON:API Resource | Vben 不使用该格式；中后台场景收益小 |
| 仓储模式（prettus/l5-repository） | Eloquent 已是数据访问层，额外分层只增加样板 |
| Fractal | 官方 API Resource 已覆盖 |
| dingo/api | 已停止维护（止步 Laravel 8） |
| 自动生成 REST 的包（Orion、Lomkit） | 权限与业务规则难以精细控制；已有代码生成器 |
| 响应格式包 | 约 30 行即可实现 |
| Ray、Xdebug、Blackfire | 个人工具，不进依赖 |

## 13. 自建基础设施清单

只有下表中的能力需要自己写代码，其余全部由 Laravel 或 §12 中的包提供。每一项都尽量做薄：只写官方能力之上的“粘合层”。

| 能力 | 自建内容 | 依托的官方能力 / 包 |
|---|---|---|
| Vben 响应格式 | 响应包装中间件 + 响应宏 | 框架响应宏 |
| 菜单权限 | 菜单树（目录 / 菜单 / 按钮）、内置节点同步命令、按 Vben 路由结构输出菜单、按钮权限码接口 | spatie/laravel-permission、Gate |
| 数据权限 | 范围提供者接口、默认“全部 / 仅本人”、模型 trait | Eloquent 查询范围、Policy |
| 认证补充 | 登录日志、在线会话与强制下线、禁用账号立即生效、设备 token 签发、指定角色强制两步验证 | Fortify、Sanctum、认证事件、数据库会话 |
| 操作日志 | 记录写请求的中间件（脱敏、请求 ID），写入 activitylog | spatie/laravel-activitylog、`Context` |
| 字典 | 字典类型 / 字典项、缓存、`InDict` 校验规则、选项下发接口 | Cache、Validation |
| 系统参数 | 后台可管理的键值参数、按类型转换、缓存、公开参数接口 | Cache、Casts |
| 附件 | 上传记录、归属校验、删除时清理文件 | Storage |
| 通知中心 | 我的通知、已读、全部已读接口 | Laravel 数据库通知 |
| 公告 | 公告管理、发布后分批群发 | Notifications、`Bus::batch` |
| 定时任务 / 失败任务 | 列表、立即执行、重试、删除接口 | spatie/laravel-schedule-monitor、`failed_jobs` |
| 系统监控 | 健康状态与系统信息接口 | spatie/laravel-health、Pulse |
| 枚举选项 | 统一枚举接口 + 选项下发接口 | PHP 枚举 |
| 异步导出 | 导出任务、完成通知、下载 | simple-excel、队列、通知 |
| 富文本净化 | 保存时净化的统一入口 | symfony/html-sanitizer |
| 代码生成器 | `make:module`、`make:app`、定制 stub | 官方 `make:*` 命令、`stub:publish` |
| 架构与约定测试 | 边界、规范的自动检查 | Pest 架构测试、PHPStan |

## 14. 待决事项

| 事项 | 现状 |
|---|---|
| 迁移文件位置 | 建议保留在 `database/migrations`（官方命令默认位置，全局顺序可控） |
| 附件 | 暂保留自建，需要缩略图时换 medialibrary |
| PHP 8.5 工具链兼容性 | 待在 8.5 环境实测 |
| Passkeys | `laravel/passkeys` 仍为 0.x，暂缓 |
| 多租户 | 暂不做；做时采用 fail-closed 设计 |
| 后端管理 Caddy（域名、证书） | 暂不做 |
