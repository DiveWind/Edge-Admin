# 后端设计文档（目标架构）

> **状态：设计稿，尚未实施。** 本文描述下一阶段的目标架构，是后续改造的依据。
> 现有代码的实际情况见 [architecture.md](architecture.md)、[backend-guide.md](backend-guide.md)。
> 两者冲突时：已实施的行为以现有文档为准，新开发按本文执行。
>
> - 项目名：**待定**（现名 Edge Admin 与已有开源项目重名，开源前更换，见 [§13](#13-待决事项)）。文中暂用“本框架”。
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
13. [待决事项](#13-待决事项)
14. [附录：设计来源与取舍](#14-附录设计来源与取舍)

---

## 1. 定位

一个**纯 API 的 Laravel 后台框架**，默认适配 [Vben Admin 5](https://github.com/vbenjs/vue-vben-admin)（antdv-next 版），但本身不绑定任何前端。

- 提供 RBAC、数据权限、动态菜单、日志、通知、字典、参数、附件、定时任务等后台通用能力。
- 支持一个核心加多个独立入口（多应用），例如后台 API、设备接入 API、开放平台 API。
- 目标是**可开源的通用级框架**：依赖少、几乎全为官方或社区事实标准，规范可被测试和工具强制执行。

**不做的事**：不渲染页面（没有 Blade 后台、没有 Livewire）；不在后端定义前端的表单、表格、布局。

## 2. 设计原则

1. **官方优先。** Laravel 官方包能做的，不引入第三方；官方没有的，用社区事实标准（spatie）；都没有的才自己写。
2. **前后端彻底分离。** 后端只提供数据和规则，界面完全由前端决定。参考 Nova、Filament 这类“前后端一体”的产品时，只借鉴后端部分。
3. **业务规则只有一份，在核心里。** 无论从后台、设备、命令行还是队列进入，执行的都是同一套规则。
4. **默认拒绝。** 授权、数据权限、多租户隔离一律 fail-closed：缺规则 = 不允许。
5. **规范要能被机器检查。** 能用 Pest 架构测试、PHPStan、Pint、Rector 强制的约定，就不只写在文档里。
6. **选定一种写法就全项目统一，并写进代码生成器。** 一致性比选哪一种更重要。
7. **为常驻内存而写。** 代码默认运行在 Octane（FrankenPHP worker 模式）下。

## 3. 技术栈

| 层 | 选型 |
|---|---|
| 语言 | PHP 8.5 |
| 框架 | Laravel 13 |
| 运行时 | Laravel Octane + FrankenPHP（worker 模式，内嵌 Caddy） |
| 认证 | Laravel Sanctum（token 模式） |
| 授权 | Gate + Policy + spatie/laravel-permission |
| 数据库 | MySQL / PostgreSQL / SQLite（代码不写方言 SQL） |
| 缓存 / 队列 | Redis（生产）；database 驱动（开发可用） |
| 前端（默认适配） | Vben Admin 5 + antdv-next，独立仓库目录 `web/` |
| 开发环境 | Linux：Sail；Windows：WSL2（或 Sail） |

## 4. 仓库与目录结构

### 4.1 仓库根目录

```
.
├── server/          Laravel 后端（本文主体）
├── web/             Vben 前端（monorepo，保留 apps/web 与 playground）
├── docs/            项目文档
├── deploy/          Caddyfile、Dockerfile、Supervisor 配置等部署文件
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
│   │   ├── System/                   用户、角色、菜单权限、部门、岗位、字典、参数、附件
│   │   ├── Monitor/                  登录 / 操作 / 数据变更日志、在线会话、定时任务、系统信息
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
│   │   └── AdminServiceProvider.php
│   │
│   ├── Device/                       应用：设备接入（受限，以后加）
│   │   ├── Http/ ...
│   │   ├── routes/api.php
│   │   └── DeviceServiceProvider.php
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

模块资源通过 ServiceProvider 注册：`loadTranslationsFrom()`、`loadViewsFrom()`，使用时带命名空间，如 `__('system::messages.x')`。需要浏览器直接下载的文件放 `resources/public`，部署时 `vendor:publish` 到 `public/vendor/<模块>`。

**`app/` 目录绝不对外访问**；Web 根目录只能是 `public/` 和前端构建产物。

### 4.4 命名约定

- **应用按“给谁用”命名**：`Admin`（管理员后台）、`Device`（设备）、`Portal`（用户端）、`Open`（开放平台）、`Merchant`（商户后台）……
- **核心叫 `Core`**，业务模块用复数含义的业务名（`System`、`Monitor`、`Message`、`Content`）。
- 应用内部的目录与核心模块**同名一一对应**。删除一个业务 = 删除 `Core/<模块>` + 各应用里的同名目录 + 前端对应页面。

### 4.5 偏离 Laravel 默认目录的代价与处理

核心不在 `app/Models` 等默认位置，以下约定需显式配置：

| 机制 | 处理 |
|---|---|
| 工厂推断 | 模型统一用 `#[UseFactory]` 显式声明 |
| Policy 推断 | `X\Models` → `X\Policies` 的默认规则可用；不满足时用 `#[UsePolicy]` |
| 命令、事件监听发现 | `bootstrap/app.php` 中 `withCommands()`、`withEvents(discover: [...])` 指定路径 |
| 认证用户模型 | `config/auth.php` 指向 `App\Core\System\Models\User` |
| 多态类型 | 启用 `Relation::enforceMorphMap()`，库里存短别名 |
| 官方 make 命令 | 带完整命名空间，如 `make:model Core/System/Models/Dept`；日常用生成器 |

## 5. 核心与应用：分层和依赖边界

### 5.1 三档访问权限

| 谁 | 读数据 | 写数据 |
|---|---|---|
| **Core** | 随意 | 随意（规则的拥有者） |
| **受信任的应用**（Admin） | 直接使用核心模型：查询、路由模型绑定、API Resource 输出 | **必须调用核心的 Action**，禁止直接 `save()` / `update()` / `delete()` / `create()` |
| **受限的应用**（Device 等） | 只能通过 `Core\Contracts` 拿 `Core\Data` | 只能通过 `Core\Contracts` |

理由：Admin 要用的几乎是核心全部数据，强制走接口等于用接口重写一遍 Eloquent；受限应用只需要两三个能力，且不应知道内部结构。

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
| Action（核心） | 多步写入、事务、业务约束（唯一性、状态流转、引用检查）、触发事件 | 依赖 HTTP 层 |
| Query（核心） | 复杂查询、统计指标（独立类，统一缓存策略） | 写数据 |
| Policy（核心） | 记录级授权、数据权限判定 | 依赖调用方 |
| Contracts / Data（核心） | 受限应用的唯一入口 | 暴露模型 |

单表、无副作用的增删改也必须经过 Action（保证“写只有一个入口”），由生成器自动生成，不增加手写成本。

### 5.4 强制手段

- **Pest 架构测试**：上述依赖规则；`App\Admin` 不得依赖 `DB` 门面；应用内不得出现 `Models` 命名空间；`App\Device` 不得依赖 `App\Core\*\Models`。
- **“写必须走 Action”**：Pest 无法检测方法调用，使用 PHPStan 扩展 `spaze/phpstan-disallowed-calls` 禁止 `App\Admin` 中调用模型写方法（落地前核实版本）。
- **代码审查清单**写入 `CONTRIBUTING.md`。

### 5.5 应用的注册与运行

- 每个应用在自己的 ServiceProvider 中注册：路由（前缀或域名）、中间件组、认证守卫、限流器、异常输出格式。
- `config/edge.php`（随项目改名）登记应用清单；进程按环境变量只加载部分应用，例如 `APPS=admin,device`。未加载的应用其路由在该进程中不存在。
- 对外开放的应用（Device、开放平台）路由**从第一版起带版本号** `/v1`；Admin 只服务自家前端，不带版本号。
- 对外开放的应用暴露的资源使用 **ULID**（`HasUlids`），不暴露自增 ID。

## 6. 与 Vben 的接口契约

以下约定只属于 **Admin 应用**；其他应用可以有自己的格式。

### 6.1 响应格式

- **HTTP 状态码照常使用**（200 / 201 / 204 / 401 / 403 / 404 / 422 / 429 / 500）。
- 响应体统一包装为 Vben 默认格式，由 Admin 的中间件 + 响应宏完成，控制器无感知：

```json
// 成功
{ "code": 0, "data": { ... }, "message": "ok" }
// 分页
{ "code": 0, "data": { "items": [ ... ], "total": 123 }, "message": "ok" }
// 失败（HTTP 422）
{ "code": 422, "data": null, "message": "数据校验失败", "errors": { "username": ["..."] } }
```

- 不引入响应格式包，自己实现（约 30 行）。

### 6.2 认证与菜单相关接口

| 接口 | 说明 |
|---|---|
| `POST /auth/login` | 返回 `accessToken`；开启两步验证时返回挑战凭证（见 §7.3） |
| `POST /auth/logout` | 吊销当前 token |
| `GET /auth/codes` | 当前用户的按钮权限码列表 |
| `GET /user/info` | 当前用户信息 |
| `GET /menu/all` | 当前用户可见的菜单树，结构与 Vben 路由一致：`path`、`component`、`meta { title, icon, order, ... }` |

Vben 的“刷新令牌”功能关闭；token 过期即重新登录。

### 6.3 列表查询

- 参数：分页、排序、筛选，使用 `spatie/laravel-query-builder`，**允许的筛选和排序字段显式白名单**。
- 返回 `{ items, total }`。

## 7. 认证

### 7.1 方案

**Sanctum token 模式**，后台、App、设备统一使用。

- 不使用 JWT：无法立即吊销，后台必需的“强制下线、禁用立即生效”会变弱。
- 不使用 Cookie 会话：已确认前后端可能跨域部署，且 Vben 默认即 token 模式。
- 以后做开放平台、第三方授权登录时，再引入 **Passport**（OAuth2）。

### 7.2 功能拆分

| 功能 | 实现 |
|---|---|
| 登录、登出、发 token | 自己实现（Fortify 基于会话，不适用 token 模式） |
| 登录限流 | 只累计失败、成功清零；次数由系统参数控制 |
| 找回密码、邮箱验证 | Laravel 框架自带的 Password broker 与邮箱验证 |
| 两步验证 | 使用 **Fortify 的两步验证部件**（密钥、二维码、恢复码、加密存储），`Fortify::ignoreRoutes()` 关闭其全部路由 |
| 在线会话、强制下线 | 基于 token 表（含 IP、UA、最后活跃时间） |

### 7.3 两步验证流程

1. `POST /auth/login`：账号密码正确且用户启用了两步验证 → 不发 token，返回 `two_factor_required` 与一个短期挑战凭证（缓存，5 分钟过期）。
2. `POST /auth/two-factor-challenge`：挑战凭证 + 动态码或恢复码 → 验证通过后签发 Sanctum token。

设计上做成**可插拔的验证提供者**：首批实现 TOTP；预留邮箱、短信验证码；支持“指定角色必须开启两步验证”。

### 7.4 token 安全措施

- token 设置过期时间（普通登录与“记住我”区分）；
- 启用 Sanctum `token_prefix`，便于密钥扫描识别泄露；
- 设备等机器 token 用 abilities 限定权限范围；
- 改密码、禁用账号、强制下线时吊销对应 token；
- 定时清理过期 token；
- token 存在浏览器 localStorage，**XSS 是主要风险**，配套措施见 §9。

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

- 权限表扩展为菜单树：目录 / 菜单 / 按钮三种类型，含路径、组件、图标、排序、显示、状态。
- 内置节点在配置中声明并同步（标记为内置，不可删除、不可改权限码）；管理员在界面上的调整在再次同步时不被覆盖。
- 停用的节点及其子孙对所有人（含超管）失效；为防止自锁，菜单管理相关节点不可停用。

### 8.3 Policy 规范

- **标准方法齐全**：`viewAny`、`view`、`create`、`update`、`delete`、`restore`、`forceDelete`，以及批量的 `deleteAny`、`restoreAny`、`forceDeleteAny`。
- **关联操作单独授权**：如分配角色、分配部门、挂载分类，各有对应方法。
- 权限码检查放在 Policy 内部；控制器只声明 `#[Authorize('update', 'user')]` 这类标准写法。
- **默认拒绝**：测试强制要求每个模型都有 Policy 且标准方法齐全；每条受保护路由都有授权声明。
- 批量操作：默认检查一次批量方法；需要时逐条检查。

### 8.4 数据权限

- 范围：全部、自定义部门、本部门及下级、本部门、仅本人；多角色取并集。
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
| XSS 纵深防御 | Caddy 下发 CSP 响应头；前端不直接渲染用户输入的 HTML |
| 依赖 | CI 执行 `composer audit`、`npm audit` |
| 日志 | 操作日志对密码、token 等字段脱敏；每个响应带 `X-Request-Id` |

## 10. 编码规范

### 10.1 Laravel 写法

- 授权：控制器方法上用 `#[Authorize]`；路由文件只描述结构（分组导入、`apiResources`）。
- 模型：`#[Fillable]`、`#[Hidden]`、`#[UseFactory]`、`#[ObservedBy]` 等 Attribute；`casts()` 方法；禁止 `$fillable` 等属性写法。
- 命令：`#[Signature]`、`#[Description]`。队列任务：`#[Tries]`、`#[Backoff]` 等，**队列名不写在任务上**，统一用 `Queue::route()` 集中登记。
- 统计类数据用 `Cache::flexible()`；请求内缓存用 `Cache::memo()` 或 `#[Scoped]`。
- 时间：写库前统一转换为应用时区；输出带时区偏移的 ISO 8601。
- 枚举：实现统一的 `HasLabel`（多语言）/ `HasColor`（语义色，如 success / warning）接口，由后端统一下发选项。
- 统计指标、复杂查询做成核心模块里独立的 Query 类。
- 软删除是整套功能：恢复、彻底删除、“已删除”筛选、对应授权，生成器一个参数生成。
- 导出：`simple-excel` + 队列 + 完成通知，标准异步导出。
- 对接第三方 API 时统一使用 Saloon（不作为核心依赖）。

### 10.2 PHP 8.4 / 8.5 特性

| 特性 | 用法 |
|---|---|
| `#[\NoDiscard]` | 权限、数据权限、校验类方法：返回值不用即告警 |
| `clone($obj, [...])` | 只读数据对象的 `withXxx()` 方法 |
| 管道运算符 `\|>` | 串联普通函数、纯函数；不替换已有的 `Str` / 集合链式写法 |
| 属性钩子、不对称可见性 | 用于数据对象、值对象、服务类；**不用于 Eloquent 模型字段** |
| `new X()->method()` | 去掉多余括号 |
| `array_find` / `array_any` / `array_all` / `array_first` / `array_last` | 替代简单循环 |
| `#[\Deprecated]` | 公开 API 废弃流程（对开源框架尤为重要） |
| `Uri\Rfc3986\Uri` | 网址解析与校验 |

**前提**：落地前确认 Pint、PHPStan / Larastan、Rector、Pest、Scramble 全部支持所用新语法；先由 Rector 批量升级，再人工处理。

### 10.3 常驻内存（Octane）规则

- 禁止用静态属性或单例保存请求相关数据；需要请求级状态时用 `#[Scoped]`、`Cache::memo()`、`Context`。
- 禁止在请求中修改 `config()`。
- 禁止 `exit()`、`die()`。
- 引入第三方包前确认其支持 Octane；有状态的服务登记到 Octane 的 `flush` 配置。
- 以上能自动检查的（静态属性、`exit`）加入架构测试。

### 10.4 代码生成

- `edge:make-module`（随项目改名）：在核心生成业务模块，同时在 Admin 生成对应的同名目录；底层编排官方 `make:*` 命令与定制 stub。
- `edge:make-app`：新建一个应用（Provider、路由、中间件组、测试目录、架构测试登记）。
- 生成内容包括：模型、迁移、工厂、Action、Policy（标准方法齐全）、Request、Resource、控制器、权限节点、路由、操作日志文案、**授权测试**、模块 README。
- **新建文件必须使用生成器**，写入 `CLAUDE.md`。

### 10.5 测试

- Pest；`tests/` 目录与 `app/` 对应。
- `tests/Pest.php` 提供统一辅助：以某权限登录、断言无权限返回 403、断言数据权限过滤。
- 每个接口至少覆盖：成功、无权限 403、数据权限之外 403/404、校验失败 422。
- 核心安全模块（认证、权限、数据权限）运行 Pest 变异测试。
- CI 在 Linux + PHP 8.5 + Octane 下运行全部测试。

## 11. 运行、开发与部署

### 11.1 开发环境

| 系统 | 方式 |
|---|---|
| Linux | **Sail**（团队标准）；或本机 PHP 8.5 + Docker 跑数据库等服务 |
| Windows | **WSL2**（项目放在 WSL 文件系统内）；或 Sail |
| 原生 Windows + Herd | 仅用于编写普通代码：Octane / FrankenPHP、Horizon 不可用，不作为标准环境 |

开发时同样用 `octane:start --server=frankenphp --watch` 运行，尽早暴露常驻内存问题。前端开发时用 Vite 代理把 `/api` 转发到后端。

### 11.2 部署形态

**一个镜像，两个目录，Caddy 分流**：

```
/app/server/        Laravel（public/ 只放 Laravel 自身文件）
/app/web/dist/      Vben 构建产物
```

- `/api/*`、`/storage/*`、`/up` → Laravel（Octane worker）；
- 其他路径 → `web/dist` 静态文件，找不到时回退到 `index.html`；
- 带哈希的静态资源长期缓存，`index.html` 不缓存；
- HTTPS 由 Caddy 自动申请证书。

Dockerfile 多阶段构建：Node 构建前端 → Composer 安装后端 → `dunglas/frankenphp:php8.5` 最终镜像。Caddyfile 以 Octane 自带版本为基础扩展，放在 `deploy/`。构建产物不进 git。

### 11.3 进程

| 进程 | 说明 |
|---|---|
| Octane（FrankenPHP） | HTTP，可按应用拆分多个进程 |
| 队列 | Horizon（Redis）或 `queue:work`，由 Supervisor / systemd 管理 |
| 调度 | `schedule:work` 或 cron |
| Reverb | 启用实时推送时 |

### 11.4 发布流程

1. 数据库迁移（迁移必须向下兼容）；
2. 切换新镜像；
3. `php artisan optimize`；
4. `php artisan octane:reload`（**必需**，否则仍运行内存中的旧代码）。

### 11.5 本地与线上的差异清单

| 差异 | 保证手段 |
|---|---|
| 常驻内存 | §10.3 规则 + CI 在 Octane 下测试 |
| 文件名大小写、换行符 | CI 在 Linux 运行；`.gitattributes` 统一 LF |
| Windows 缺失组件（Horizon、`df` 等） | 按环境注册，配置开关隔离 |
| HTTPS、代理 | `APP_URL` 用 https；配置可信代理 |
| 上传大小 | PHP 与 Caddy 的限制一致 |
| PHP 版本与扩展 | `composer.json` 固定平台为 8.5；必要扩展用官方 Docker 镜像安装 |

## 12. 依赖清单

### 12.1 运行时依赖

**Laravel 官方**

| 包 | 用途 |
|---|---|
| laravel/framework 13 | 框架 |
| laravel/sanctum | 认证（token） |
| laravel/fortify | 仅使用两步验证部件，关闭路由 |
| laravel/octane 2.25 | 常驻内存运行 |
| laravel/reverb 1.14 | 实时推送（可选） |
| laravel/pulse 1.11 | 生产性能监控 |
| laravel/horizon 5.52 | Redis 队列管理（需 pcntl，Linux） |
| laravel/pennant 1.28 | 功能开关（可选） |

**spatie**

| 包 | 用途 |
|---|---|
| laravel-permission 8.5 | 角色与权限存储 |
| laravel-query-builder 7.3 | 列表筛选、排序、关联加载 |
| laravel-activitylog 5.1 | 数据变更审计 |
| laravel-backup 10.4 | 数据库与文件备份 |
| laravel-health 1.42 | 健康检查（替代自写的系统信息检查） |
| laravel-schedule-monitor 4.3 | 定时任务监控（替代自写的执行日志） |
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

`saloonphp/saloon`（对接第三方 API）、`deptrac/deptrac`（模块变多后的分层可视化）、`spatie/laravel-medialibrary`、`laravel-model-states`、`laravel-pdf`、`laravel-webhook-*`、`laravel-multitenancy`、`laravel/passport`（开放平台）、`laravel/mcp`（AI 调用后台能力）。

### 12.4 明确不使用

| 不用 | 原因 |
|---|---|
| JWT（tymon/jwt-auth 等） | 无法立即吊销；Laravel 无官方包 |
| JSON:API Resource | Vben 不使用该格式；中后台场景收益小 |
| 仓储模式（prettus/l5-repository） | Eloquent 已是数据访问层，额外分层只增加样板 |
| Fractal | 官方 API Resource 已覆盖 |
| dingo/api | 已停止维护（止步 Laravel 8） |
| 自动生成 REST 的包（Orion、Lomkit） | 权限与业务规则难以精细控制；已有代码生成器 |
| 响应格式包 | 30 行自写即可 |
| Ray、Xdebug、Blackfire | 个人工具，不进框架依赖 |

## 13. 待决事项

| 事项 | 现状 |
|---|---|
| 项目名 | 待定。候选 KeelAdmin、StrataAdmin、OctaAdmin；需确认 GitHub 组织名、域名、商标 |
| 迁移文件位置 | 建议保留在 `database/migrations`（官方命令默认位置，全局顺序可控），文件名带模块前缀；待确认 |
| 字典、系统参数、附件的去留 | 系统参数保留自研（界面可管理的键值对）；附件暂保留，需要缩略图时换 medialibrary |
| PHP 8.5 工具链兼容性 | 待在 8.5 环境实测 |
| Passkeys | `laravel/passkeys` 仍为 0.x，暂缓 |
| 多租户 | 暂不做；做时采用 fail-closed 设计 |
| 后端管理 Caddy（域名、证书） | 暂不做；需要时采用 On-Demand TLS + 内部询问接口方案 |

## 14. 附录：设计来源与取舍

| 来源 | 借鉴 | 不借鉴 |
|---|---|---|
| Filament 5 | Panel 多入口、标准 Policy 方法（含批量、软删除）、安全规范（查询范围、字段暴露、URL 与 HTML 净化、上传）、枚举接口、多因素认证结构、模块化（按业务纵向切分） | 用 PHP 定义表单与表格、Livewire 相关、缺 Policy 时默认放行 |
| Nova（仅公开的示例应用与旧版文档） | 关联操作授权、统一查询入口施加数据权限、Action 的可见与可执行分开授权、命名查询与指标 | 后端定义界面、Policy 中判断调用方（反面教材） |
| Apiato / Porto | 按业务纵向切分、对外接口版本化、不暴露自增 ID、模块 README、强制使用生成器 | 仓储模式、Fractal、处处继承父类、过多层级；尚不支持 Laravel 13 |
| madong、SaiAdmin 等 Webman 系 | — | 租户隔离默认放行、依赖混杂、缺少测试与 CI |
| CatchAdmin | — | GET 请求默认不检查权限 |
