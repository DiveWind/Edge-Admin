# 后端设计文档（目标架构）

> **状态：设计稿，边实施边修订。** 本文是后端设计的唯一依据；代码与本文冲突时以本文为准，需要调整设计时先改本文。
>
> - 定位为**脚手架（Starter）**，不设品牌名；文中称“本脚手架”。
> - 库版本号核查于 2026-11，落地时以 Packagist / npm 当时的最新稳定版为准。

## 目录

1. [定位](#1-定位)
2. [设计原则](#2-设计原则)
3. [技术栈](#3-技术栈)
4. [仓库与目录结构](#4-仓库与目录结构)
5. [分层与依赖边界](#5-分层与依赖边界)
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
- 三类代码各归其位：**内核（Core）**放通用后台能力、尽量不改；**业务模块（Modules）**放项目业务；**入口（Http）**只开放接口，支持多个独立入口，例如后台 API、设备接入 API、开放平台 API。
- **尽量少自建基础设施**：能用 Laravel 官方能力或社区事实标准实现的，一律不自己写；必须自建的部分全部登记在 [§13](#13-自建基础设施清单)。

**不做的事**

- 不渲染页面（没有 Blade 后台、没有 Livewire）；不在后端定义前端的表单、表格、布局。
- 不内置部门、岗位等组织架构。需要时由业务按需加入，数据权限预留了扩展点（见 [§8.4](#84-数据权限)）。

## 2. 设计原则

1. **官方优先，少自建。** Laravel 官方包能做的，不引入第三方；官方没有的，用社区事实标准（spatie）；都没有的才自己写，并登记在 §13。
2. **前后端彻底分离。** 后端只提供数据和规则，界面完全由前端决定。
3. **业务规则只有一份，在模块里。** 无论从后台、设备、命令行还是队列进入，执行的都是同一套规则；入口只开放接口。
4. **内核尽量不改。** 业务模块通过 Core 的扩展点接入，不修改 Core 的文件。
5. **默认拒绝。** 授权、数据权限、多租户隔离一律 fail-closed：缺规则 = 不允许。
6. **规范要能被机器检查。** 能用 Pest 架构测试、PHPStan、Pint、Rector 强制的约定，就不只写在文档里。
7. **选定一种写法就全项目统一，并写进代码生成器。** 一致性比选哪一种更重要。
8. **默认 PHP-FPM，常驻内存安全。** 默认以 PHP-FPM 运行；所有代码都按常驻内存安全来写，可无缝切换到 Octane + FrankenPHP。

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

前后端只通过 [§6](#6-与-vben-的接口契约) 的接口契约通信，互不引用代码。

### 4.2 `server/` 目录

`app/` 下每个顶层目录就是一类代码：

| 目录 | 是什么 | 改动频率 |
|---|---|---|
| `Core/` | 脚手架内核：每个项目都需要的通用后台能力 | 很少，尽量不改 |
| `Modules/` | 业务模块：项目自己的业务 | 频繁 |
| `Http/` | 入口：只负责对外开放接口（路由、控制器、参数校验、输出格式） | 随业务变化 |
| `Support/` | 与业务无关的共享基础件 | 很少 |

```
server/
├── app/
│   ├── Core/                         内核（尽量不改）
│   │   ├── System/                   用户、角色、菜单权限、字典、参数、附件
│   │   ├── Monitor/                  日志、在线会话、定时任务、系统监控
│   │   └── Message/                  公告、通知
│   │
│   ├── Modules/                      业务模块
│   │   ├── Content/                  文章（业务对接示范，可整块删除）
│   │   └── Device/                   示例：设备档案、设备配置（以后加）
│   │
│   ├── Http/                         入口
│   │   ├── Admin/                    后台 API（受信任），给 Vben 用
│   │   │   ├── System/  Monitor/  Message/     ← 对应 Core 的模块
│   │   │   ├── Content/  Device/               ← 对应业务模块
│   │   │   │   ├── Controllers/
│   │   │   │   ├── Requests/
│   │   │   │   └── Resources/
│   │   │   ├── Middleware/           本入口专用的中间件（Vben 响应包装等）
│   │   │   ├── routes.php
│   │   │   └── AdminServiceProvider.php
│   │   ├── Device/                   设备接入 API（受限，以后加）
│   │   └── Middleware/               多个入口共用的中间件（Laravel 默认位置）
│   │
│   └── Support/                      共享基础件（基类、通用工具）
│
├── bootstrap/  config/  database/  lang/  public/  storage/
├── routes/console.php                只放调度定义
├── stubs/                            定制后的官方 stub + 生成命令模板
└── tests/
    ├── Core/{System,Monitor,Message}/
    ├── Modules/{Content,Device}/
    ├── Http/{Admin,Device}/
    └── Arch/                         边界与约定测试
```

### 4.3 模块内部结构

Core 与 Modules 下的模块结构相同，按需创建子目录，不建空目录：

```
app/Modules/Content/
├── Models/            Eloquent 模型
├── Actions/           写操作用例（一个类一个用例，handle() 方法）
├── Policies/          授权规则
├── Queries/           复杂查询、统计指标（只读）
├── Enums/             枚举（实现统一的 HasLabel / HasColor 接口）
├── Events/  Listeners/  Observers/  Jobs/  Notifications/
├── Rules/  Casts/
├── Contracts/  Data/  Services/   需要向受限入口开放时才有：接口、只读数据对象（`<Name>Data`）与接口的实现
├── resources/
│   ├── lang/          模块自己的语言包（命名空间 content::）
│   ├── views/         邮件、PDF、导出模板（不对外访问）
│   └── menus.php      本模块的菜单与权限节点声明（§8.2）
├── README.md          模块说明：职责、对外提供什么、依赖了 Core 的哪些能力
└── ContentServiceProvider.php    注册本模块的命令、监听、语言包、视图，并通过扩展点接入 Core（§5.5）
```

需要浏览器直接下载的文件放 `resources/public`，部署时 `vendor:publish` 到 `public/vendor/<模块>`。**`app/` 目录绝不对外访问**；Web 根目录只能是 `public/` 和前端构建产物。

### 4.4 命名约定

- **入口按“给谁用”命名**：后台（`Admin`）、设备（`Device`）、用户端（`Portal`）、开放平台（`Open`）……
- **Core 的模块**：`System`、`Monitor`、`Message`。**业务模块**用业务名（`Content`、`Device`、`Order`……）。
- 入口内部的目录与模块**同名一一对应**。删除一个业务 = 删除 `Modules/<模块>` + 各入口里的同名目录 + 前端对应页面，Core 不受影响。
- `App\Modules\Device`（设备业务）与 `App\Http\Device`（设备接入入口）同名，但命名空间不同、职责不同。

### 4.5 偏离默认目录：官方支持与需要的配置

官方文档（Directory Structure）明确：默认结构只是起点，可以按需组织，“只要 Composer 能自动加载，Laravel 几乎不限制任何类的位置”。模块与入口采用官方“包开发”（Package Development）的方式注册自己的路由、命令、语言包和视图。

| 默认行为 | 是否受影响 | 处理（均为官方机制） |
|---|---|---|
| Policy 自动推断 | **不受影响** | 框架会把模型命名空间中的 `\Models\` 替换为 `\Policies\` 查找，`App\Core\System\Models\User` 自动对应 `App\Core\System\Policies\UserPolicy` |
| 工厂推断 | 模型 → 工厂**不受影响**；工厂 → 模型需补一次配置 | `App\Modules\Content\Models\Article` 默认对应 `Database\Factories\Modules\Content\Models\ArticleFactory`，`make:model ... -f` 也按此生成。反向推断默认回退到 `App\`，在 `AppServiceProvider` 中用 `Factory::guessModelNamesUsing()` 补上对称规则即可 |
| 观察者、任务、通知、规则、类型转换、枚举、Action | **不受影响** | 无自动发现机制，放在哪里都可用；观察者用 `#[ObservedBy]` 绑定 |
| 命令自动发现 | 受影响（默认只扫描 `app/Console/Commands`） | 模块 Provider 中 `$this->commands([...])`（包开发方式） |
| 事件监听自动发现 | 受影响（默认只扫描 `app/Listeners`） | `withEvents(discover: [...])` 列出各模块的 `Listeners` 目录，或在模块 Provider 中显式注册 |
| 路由、语言包、视图 | 需注册 | Provider 中 `loadRoutesFrom()`、`loadTranslationsFrom()`、`loadViewsFrom()`，路由缓存照常可用 |
| 迁移 | **不受影响** | 保留在 `database/migrations` |
| 认证模型、权限模型 | 需配置 | `config/auth.php`、`config/permission.php` 指向 Core 的模型 |
| 多态类型 | **必须第一天配置** | `AppServiceProvider` 中开启 `Relation::enforceMorphMap()`；各模块在自己的 Provider 中用 `Relation::morphMap()` 登记别名（业务模块 `<模块>.<模型>`，内核模块 `<模型>`）。否则通知、日志、token、角色关联等表会存入完整类名，以后移动类就要迁移数据（官方文档的提醒） |
| 官方 `make:*` 命令 | 受影响（默认命名空间固定） | 名称一律写以 `App/` 开头的完整路径，如 `make:controller App/Http/Admin/System/Controllers/UserController`；日常使用 `core:make-*` 生成命令（§10.4） |

## 5. 分层与依赖边界

### 5.1 四类代码的依赖方向

```
Http（入口）  →  Modules（业务）  →  Core（内核）  →  Support
      └───────────────→───────────────┘
```

| 类别 | 可以依赖 | 不能依赖 |
|---|---|---|
| Core | Support | Modules、Http |
| Modules | Core、Support | Http；其他业务模块（默认不允许，需要时通过事件或对方的 Contracts） |
| Http（入口） | Core、Modules、Support（按信任级别，见 §5.2） | 其他入口 |
| Support | 框架本身 | 其他三类 |

“领域层”指 Core 与 Modules 的合称：所有模型、业务规则、授权规则都在领域层，入口只开放接口。

### 5.2 入口的两档访问权限

| 入口 | 读数据 | 写数据 |
|---|---|---|
| **受信任的入口**（Admin） | 直接使用 Core 与 Modules 的模型：查询、路由模型绑定、API Resource 输出 | **必须调用对应模块的 Action**，禁止直接 `save()` / `update()` / `delete()` / `create()` |
| **受限的入口**（Device 等） | 只能通过模块导出的 `Contracts` 拿 `Data` | 只能通过 `Contracts` |

理由：后台要用的几乎是全部数据，强制走接口等于用接口重写一遍 Eloquent；受限入口只需要两三个能力，且不应知道内部结构。

### 5.3 依赖规则

- Core 不依赖 Modules 和入口；Modules 不依赖入口；入口之间互不依赖。
- 入口里不得定义模型、迁移和业务规则：**入口只开放接口**。
- Modules 可以直接使用 Core（如关联用户、读取系统参数、使用数据权限 trait），但**不修改 Core 的代码**；需要 Core 配合的，一律通过扩展点（§5.5）。
- Policy 属于模块，**与调用方无关**：禁止出现“判断是哪个入口在调用”的分支。
- 模块通知入口的唯一方式是事件；入口不被模块直接调用。
- 受限入口可以使用缓存、日志、限流等**临时或无状态**的基础设施，但需要持久保存的业务数据一律交给模块。
- 入口之间不直接通信，一律通过模块中转（§5.8）。

### 5.4 各层职责

| 层 | 职责 | 不做 |
|---|---|---|
| Controller（入口） | 授权声明 → 调用 Action / 查询 → 返回 Resource | 业务逻辑、事务 |
| Form Request（入口） | 输入格式校验（必填、长度、类型、白名单字段） | 业务约束 |
| API Resource（入口） | 按该入口的前端格式输出，**逐个列出字段** | 查询 |
| Action（模块） | 写入、事务、业务约束（唯一性、状态流转、引用检查）、触发事件 | 依赖 HTTP 层 |
| Query（模块） | 复杂查询、统计指标（独立类，统一缓存策略） | 写数据 |
| Policy（模块） | 记录级授权、数据权限判定 | 依赖调用方 |
| Contracts / Data（模块） | 受限入口的唯一入口 | 暴露模型 |

所有写入都经过 Action（保证“写只有一个入口”）；单表、无副作用的 Action 由生成命令自动生成，不增加手写成本。

### 5.5 Core 的扩展点

业务模块通过以下扩展点接入 Core，**不修改 Core 的任何文件**。扩展点的接口放在提供它的 Core 模块中（如 `Core/System/Contracts`），由业务模块在自己的 ServiceProvider 里注册。

| 扩展点 | 业务模块怎么用 |
|---|---|
| 菜单与权限节点 | 在 Provider 的 `boot()` 中向 `App\Core\System\Contracts\MenuRegistry` 注册本模块的目录、菜单、按钮节点；由同步命令 `core:sync-permissions` 统一写入（§8.2） |
| 数据权限范围提供者 | 实现 `App\Core\System\Contracts\DataScopeProvider` 并注册到 `DataScopeRegistry`（如按部门、区域），角色表单中自动出现（§8.4） |
| 系统参数分组 | 注册本模块的参数分组与默认值 |
| 字典类型 | 声明本模块用到的字典类型与初始字典项 |
| 日志 | 写入 activitylog 时使用本模块的日志名；操作日志文案放在模块语言包中 |
| 通知 | 定义本模块的通知类，统一出现在通知中心 |
| 异步导出 | 实现导出接口，即接入统一的异步导出、完成通知与下载 |
| 枚举选项 | 枚举实现统一接口，即可由选项接口下发给前端 |
| Core 事件 | 监听 Core 发出的事件（如用户被删除），清理本模块的数据 |

### 5.6 强制手段

- **Pest 架构测试**：§5.1、§5.3 的依赖规则；入口不得依赖 `DB` 门面；入口内不得出现模型；受限入口不得依赖任何 `Models` 命名空间；Core 不得依赖 `App\Modules`。
- **“写必须走 Action”**：Pest 无法检测方法调用，使用 PHPStan 扩展 `spaze/phpstan-disallowed-calls` 禁止 `App\Http` 中调用模型写方法（落地前核实版本）。
- **授权只走 Gate**：同样用 `spaze/phpstan-disallowed-calls` 加架构测试，禁止直接调用 spatie 的判定 API（§8.1）。
- **代码审查清单**写入 `CONTRIBUTING.md`。

### 5.7 入口的注册与运行

- 每个入口一个 ServiceProvider（如 `App\Http\Admin\AdminServiceProvider`），在 `bootstrap/providers.php` 中注册，负责本入口的全部配置：加载自己的路由文件、URL 前缀（或域名）、中间件组、认证方式、限流器、异常输出格式。新增入口 = 新增一个目录 + 一个 Provider，不改动共享文件。
- `config/core.php` 登记入口清单；进程可按环境变量只加载部分入口，例如 `CORE_APPS=admin,device`。未设置时加载全部入口；设为空时不加载任何入口（适合只跑队列、调度的进程）。未加载的入口其路由在该进程中不存在。
- 以不同的 `CORE_APPS` 运行多个进程并使用路由缓存时，每种组合设置不同的 `APP_ROUTES_CACHE`。
- 对外开放的入口（Device、开放平台）路由**从第一版起带版本号** `/v1`；后台只服务自家前端，不带版本号。
- 对外开放的入口暴露的资源使用 **ULID**（`HasUlids`），不暴露自增 ID。

### 5.8 入口之间不直接通信：以“后台管理设备配置”为例

入口之间互不调用，**都通过模块中转**。某个入口的配置、开关这类“可以在后台修改的数据”，属于对应的业务模块，不属于那个入口，也不进 Core。

```
后台界面
  → Http/Admin/Device/Controllers                 设备管理接口
  → Modules/Device/Actions/UpdateDeviceSettings   校验、保存、触发 DeviceSettingsUpdated 事件
设备请求
  → Http/Device                                   设备接入入口
  → Modules\Device\Contracts\DeviceConfig         读取配置（返回 Modules\Device\Data 中的只读对象）
```

- 全程不改 Core；后台不知道设备入口存在，设备入口也不知道后台存在。
- 简单的几个键值参数，直接使用系统参数（模块注册一个参数分组，如 `device.*`）；结构化的配置（按设备类型、列表类配置）才在模块中建表。
- **两类配置要区分**：
  - 运行时可改的业务配置（心跳间隔、允许的固件版本、是否暂停接入等）→ 存数据库，按上面的方式在后台管理。“暂停某个入口”也用这种方式：入口的中间件读取模块的开关，暂停时返回 503。
  - 部署级配置（路由前缀、加载哪些入口、限流阈值等）→ 配置文件与环境变量，改动需重新部署，不做成后台可改。

## 6. 与 Vben 的接口契约

以下约定只属于**后台入口（Admin）**；其他入口可以有自己的格式。

### 6.1 响应格式

- **HTTP 状态码照常使用**（200 / 201 / 204 / 401 / 403 / 404 / 419 / 422 / 429 / 500）。
- 响应体统一包装为 Vben 默认格式，由后台入口的中间件 + 响应宏完成，控制器与 Fortify 无感知：

```json
// 成功
{ "code": 0, "data": { ... }, "message": "ok" }
// 分页
{ "code": 0, "data": { "items": [ ... ], "total": 123 }, "message": "ok" }
// 失败（HTTP 422）
{ "code": 422, "data": null, "message": "用户名或密码错误。", "errors": { "username": ["用户名或密码错误。"] } }
```

- 422 的 `message` 取第一条具体错误，完整错误在 `errors` 中。
- 提示文案语言：按请求头 `Accept-Language`（`zh-CN` / `en-US` 等）选择，不支持的语言回退到 `APP_LOCALE`。
- **后台入口不返回 204**：没有内容的成功响应（包括 Fortify 的登出、两步验证等）统一返回 HTTP 200 与 `{ "code": 0, "data": null, "message": "ok" }`。Vben 的默认响应拦截器遇到空响应体会报错，这是唯一一处为前端调整状态码的约定。
- **403 与 422 的分工**：
  - **403**（带 `message`）：只看“操作者 + 目标记录本身”就能判定的，由 Policy 返回。包括没有权限码、超出数据范围、操作自己（删除 / 停用自己）、非超管操作超管用户、删除内置节点、删除 `super-admin` 角色。
  - **422**（带字段错误 `errors`）：取决于提交内容或全局状态的，由 Action 抛出，错误挂在对应字段上。包括停用 `super-admin` 角色或生命线节点（`status`）、最后一个超管（停用挂在 `status`，去掉超管角色挂在 `roles`）、内置节点改 `name` / `type` / `path` / `component`、节点类型层级不合法、授予的权限码超出自身范围（`permissions`）、非超管授予或收回 `super-admin`（`roles`）、唯一性冲突。
  - 删除类的 422（有子节点不能删、角色已分配不能删、删除最后一个超管）没有对应的表单字段，用 `message` 说明即可。
  - 逐条标注见 §8.6。

### 6.2 认证与菜单相关接口

| 接口 | 提供方 | 说明 |
|---|---|---|
| `GET /sanctum/csrf-cookie` | Sanctum（登记在后台入口下） | 登录前获取 CSRF 令牌（`XSRF-TOKEN` Cookie） |
| `POST /login`、`POST /logout` | Fortify | 登录只接受 `username`、`password`、`remember`；返回 `{ two_factor: true/false }` |
| `POST /two-factor-challenge` | Fortify | 提交动态码或恢复码完成登录 |
| `POST /forgot-password`、`POST /reset-password` | Fortify | 找回密码；返回 `{ message }`。找回密码**无论邮箱是否存在都返回成功**，不暴露哪些邮箱已注册 |
| 两步验证的开启、确认、关闭、二维码、密钥、恢复码；确认密码；修改资料、修改密码 | Fortify | 个人设置中使用 |
| `GET /auth/codes` | 自建 | 当前用户的有效权限码（目录、菜单、按钮都含，口径见 §8.1），`string[]` |
| `GET /user/info` | 自建 | 当前用户信息；前端启动时用它判断是否已登录。字段：`userId`（字符串）、`username`、`realName`、`email`（可为 null）、`avatar`（可为 null）、`twoFactorEnabled`（两步验证已确认开启）、`roles`（启用角色的标识，即 `roles.name`，§8.5）、`desc`、`homePath` |
| `GET /menu/all` | 自建 | 当前用户可见的菜单树，结构与 Vben 路由一致（见下方“菜单节点”） |

**菜单节点**（`/menu/all` 的输出，即 Vben 的路由结构）。节点类型采用 Vben 的 5 种：

| 类型 | 含义 | `/menu/all` 中的输出 |
|---|---|---|
| `catalog` | 目录 | **不输出 `component`**，只有 `children`（vue-router 允许）。Vben 会删掉一级目录的 component，二级目录若写 `BasicLayout` 会布局套布局 |
| `menu` | 菜单（页面） | `component` 为相对 `src/views` 的路径：`/system/user/list` 对应 `src/views/system/user/list.vue` |
| `embedded` | 内嵌页 | `component: 'IFrameView'`，并输出 `meta.iframeSrc` |
| `link` | 外链 | `component: 'IFrameView'`，并输出 `meta.link` |
| `button` | 按钮 | 不出现在 `/menu/all`，只作为权限码 |

| 字段 | 规则 |
|---|---|
| `name` | 节点的**权限码**，同时是 spatie 的 permission name 与 Vben 的路由 name，**全局唯一**。小写点分：目录 `system`、菜单 `system.user`、按钮 `system.user.create` |
| `path` | 路由路径，以 `/` 开头；非按钮节点之间唯一 |
| `component` | 见上表 |
| `meta` | `title`、`icon`、`order`，以及节点 `meta` 列中的白名单键（§8.2） |
| `children` | 子节点 |

- 只输出当前用户**有效权限码**中的节点（§8.1）：节点本身及全部祖先都启用，且（超管除外）都已授予给用户的启用角色。
- 隐藏菜单的节点照常输出，只是带 `meta.hideInMenu: true`。
- 没有可见子节点的目录剔除；同级按 `meta.order` 排序。
- 不输出 `redirect`，由 Vben 自动重定向到第一个子节点。
- `meta.title` 存前端的 i18n key（或纯文本），由前端 `$t` 翻译，内置节点的 key 写在前端 locales 中。后端不翻译，因为 Vben 切换语言时不会重新请求 `/menu/all`。
- `meta.icon` 默认使用 lucide 图标集（如 `lucide:user`）。

Fortify 路由前缀与后台入口一致（`fortify.prefix`）。表中路径均相对后台入口前缀（默认 `/api/admin`）。

重置密码邮件中的链接为前端 hash 路由 `/#/auth/reset-password?token=...&email=...`（前端地址与路径可配置）。

**认证相关的其他状态码**：401 未登录，或已登录的会话所属账号被停用（停用账号尝试登录时返回 422，错误在 `errors.username`）；409 已登录时请求只限未登录的接口（如再次登录）；419 CSRF 令牌失效，前端重新获取 CSRF Cookie 后重试；423 需要先确认密码；429 登录过于频繁（带 `Retry-After`）。

找回密码邮件中的重置链接指向前端页面（`ResetPassword::createUrlUsing()`）。

**Vben 侧的调整**（都在 `apps/web` 内，约 4 个文件，共享包不改）：请求携带 Cookie 并自动带 CSRF 令牌；去掉 `Authorization` 请求头与刷新令牌逻辑；登录态改为“已登录标记 + 启动时请求 `/user/info`”；路由守卫改为判断用户信息。

**Vben 侧的权限与菜单约定**：

- 路由模式（`accessMode`）用 `mixed`：前端静态路由只放所有登录用户都有的页面（工作台、个人中心），其余全部由 `/menu/all` 下发，按 `name` 合并。
- 按钮权限直接使用节点的权限码（`v-access:code`、`hasAccessByCodes`），前端不再有独立的 `authCode` 字段。
- `/auth/codes` 在登录时和启动时（拿到 `/user/info` 之后）都重新拉取：`accessCodes` 会持久化到 localStorage，不重新拉取就会沿用旧权限。
- 菜单管理的图标选择器（IconPicker）依赖在线的 Iconify API，内网部署时无法使用；本脚手架接受这一限制。

### 6.3 列表查询

- 参数名：
  - 分页：`page`（从 1 开始）、`pageSize`（有上限）；
  - 排序：`sort`，多个字段逗号分隔，降序加 `-`，如 `sort=-created_at,id`；
  - 筛选：`filter[字段]`，如 `filter[status]=enabled`；时间区间写成 `filter[created_at]=起,止`。
- 使用 `spatie/laravel-query-builder` 实现，**允许的筛选和排序字段显式白名单**；不在白名单内的参数返回 422（该包默认抛出 400，统一映射为 422）。`failOnUnknownFields` 只检查请求体，查询参数的白名单由该包负责。
- `pageSize` 超过上限返回 422，不自动截断。
- 返回 `{ items, total }`。
- 前端提供 `toListParams` 工具：把搜索表单的值包装成 `{ page, pageSize, filter: { … } }`，并去掉空值。

### 6.4 字段命名与数据格式

- **自有资源的字段一律 snake_case**（`parent_id`、`created_at`、`data_scope`）：校验错误的键、query-builder 的字段名、前端 `setFormErrors` 都要求**表单字段名 = 接口字段名**。只有 Vben 规定的结构使用 camelCase：`/user/info`、`/menu/all` 与菜单节点的 `meta.*`，以及分页参数 `pageSize`。
- **状态统一为字符串枚举 `enabled` / `disabled`**：通用枚举 `App\Core\System\Enums\Status`（实现 `HasLabel`），用户、角色、菜单节点共用；现有的 `UserStatus` 在开发时并入 `Status`。前端适配层 `CellSwitch` / `CellTag` 的默认值相应改为 `'enabled'` / `'disabled'`。
- **时间**输出带时区偏移的 ISO 8601（§10.1），前端表格列用 formatter 格式化。
- **id** 为自增整数（对外开放的入口使用 ULID，见 §5.7）。
- **不提供“名称 / 路径是否已存在”这类检查接口**：唯一性一律在保存时由 422 返回，前端用 `setFormErrors` 回填到对应字段。

### 6.5 系统管理接口

前缀均为 `/api/admin`（下表省略）。资源路径使用 `apiResource` 的复数形式；控制器只允许资源方法和 `__invoke`，所以非资源接口都是单动作控制器。“权限”列是 Policy 检查的权限码（§8.3）。

| 接口 | 说明 | 权限 |
|---|---|---|
| `GET /auth/codes` | 当前用户的权限码 `string[]`（§6.2） | 登录即可 |
| `GET /menu/all` | Vben 路由树（§6.2） | 登录即可 |
| `GET /system/users` | 分页 `{ items, total }`；筛选 `username`、`name`、`email`、`status`、`created_at` | `system.user` |
| `GET /system/users/{user}` | 详情，含 `roles`（角色标识 `string[]`） | `system.user` |
| `POST /system/users` | 新增：`username`、`name`、`email`、`password`、`roles`、`status` | `system.user.create` |
| `PUT /system/users/{user}` | 修改：字段同新增，不含 `password` | `system.user.update` |
| `DELETE /system/users/{user}` | 删除 | `system.user.delete` |
| `PUT /system/users/{user}/status` | 启用 / 停用：`{ status }` | `system.user.status` |
| `PUT /system/users/{user}/password` | 重置密码：`{ password }`；吊销对方的会话与 token | `system.user.password` |
| `GET /system/roles/options` | 角色下拉 `[{ value: 标识, label: 显示名 }]`，只含启用角色 | `system.user.create` 或 `system.user.update` |
| `GET /system/roles` | 分页；筛选 `name`、`title`、`status` | `system.role` |
| `GET /system/roles/{role}` | 详情，含 `permissions`（权限码 `string[]`） | `system.role` |
| `POST /system/roles` | 新增：`name`、`title`、`status`、`data_scope`、`remark`、`sort`、`permissions`（`string[]`） | `system.role.create` |
| `PUT /system/roles/{role}` | 修改：字段同新增 | `system.role.update` |
| `DELETE /system/roles/{role}` | 删除 | `system.role.delete` |
| `PUT /system/roles/{role}/status` | 启用 / 停用：`{ status }` | `system.role.status` |
| `GET /system/roles/permission-tree` | 授权用的节点树：只含启用节点，精简字段 `{ name, type, meta: { title, icon }, children }` | `system.role.create` 或 `system.role.update` |
| `GET /system/roles/data-scopes` | 数据范围选项 `[{ value, label }]`（§8.4） | `system.role.create` 或 `system.role.update` |
| `GET /system/menus` | 整棵节点树（数组，**不分页**，是 §6.3 列表契约的例外） | `system.menu` |
| `GET /system/menus/{menu}` | 详情 | `system.menu` |
| `POST /system/menus` | 新增：`parent_id`、`type`、`name`、`path`、`component`、`status`、`meta.*` | `system.menu.create` |
| `PUT /system/menus/{menu}` | 修改：字段同新增 | `system.menu.update` |
| `DELETE /system/menus/{menu}` | 删除 | `system.menu.delete` |

- 菜单节点的写接口中，`meta.title`、`meta.icon` 存入 `title`、`icon` 列，`meta.order` 存入 `sort` 列（不接受顶层 `sort`），其余 `meta.*` 按白名单存入 `meta` 列（§8.2）。
- 内置节点的 `name`、`type`、`path`、`component` 在前端只读；提交的值与库中不同时返回 422（§8.6）。
- 菜单管理页拿到的节点：顶层 `id`、`parent_id`、`type`、`name`、`path`、`component`、`status`、`is_builtin`、`children`，以及 `meta`（含 `title`、`icon`、`order` 和 `meta` 列中的键）。
- 业务保护规则（不能删除自己、内置节点不能删等）见 §8.6。

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
| 修改个人资料、修改密码 | 修改密码后让其他设备下线：database 会话驱动下直接删除该用户的其他会话并吊销全部 token；其他驱动由 Sanctum 的 `AuthenticateSession` 在下次请求时注销 |
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

- 会话 Cookie：`HttpOnly`、线上 `Secure`、`SameSite=Lax`；CSRF 由 Sanctum 处理。 Laravel 13 的 CSRF 校验认可浏览器的 `Sec-Fetch-Site: same-origin`；前端与 API 不同子域时仍必须携带 `X-XSRF-TOKEN`。
- token：设置过期时间；启用 Sanctum `token_prefix` 便于识别泄露；用 abilities 限定权限范围；定时清理过期 token。
- 改密码、禁用账号、强制下线时，吊销对应的会话与 token。

## 8. 授权与数据权限

### 8.1 组件分工

```
请求 → Sanctum：你是谁
     → Gate（权限码）：你能不能做这类事
     → Policy：你能不能动这一条（业务保护、数据范围）
     → 控制器
```

- **spatie/laravel-permission** 只负责“角色与权限存在哪”：表结构、关联、分配（Action 中使用 `syncRoles()`、`syncPermissions()` 等）与缓存，不负责判定。
  - 模型 `App\Core\System\Models\Permission`、`Role` 继承 spatie 的模型，在 `config/permission.php` 中指向它们；guard 统一为 `web`（用设备 token 认证的也是同一个 `User`）。
  - `permission.register_permission_check_method = false`（不注册 spatie 自带的 Gate 检查）；`permission.register_octane_reset_listener = true`。
- **权限判定由自建的 `Gate::before` 完成**（在 `SystemServiceProvider` 中注册），只处理权限码：
  - ability 是**已登记的权限码**（以是否登记为准，不看格式；顶级目录 `system` 也是权限码）：在用户的有效权限码中 → `true`，否则 → `false`。节点或其任一祖先停用时，超管也得到 `false`。
  - 其他 ability（未登记的名称，如 Policy 方法名 `update`）→ 返回 `null`，交给 Policy。因此超管只对权限码直接放行，同样受 Policy 中记录级规则的约束（不能删除自己、不能删除内置节点等）。
- **有效权限码**由 `#[Scoped]` 的 `PermissionRegistry` 计算，每个请求只算一次。`Gate::before`、`/auth/codes`、`/menu/all` 都使用它，三处口径一致：
  - 普通用户：节点本身及全部祖先都**启用**，且都在用户**启用角色**的权限码中，该节点才生效；
  - 超管：全部启用节点（节点本身及全部祖先都启用）。
  - 保存角色权限时后端会自动补齐祖先节点（§8.5），正常情况下不会出现“有子无父”的授权。
- **禁止**直接使用 spatie 的判定 API：`hasRole()`、`hasAnyRole()`、`hasPermissionTo()`、`checkPermissionTo()`、路由宏 `->role()` / `->permission()`、`role:` / `permission:` 中间件等，它们绕过停用逻辑。由 PHPStan `disallowed-calls` 加架构测试强制；所有判定一律走 `can()` / Gate / Policy，除“是否超管”外不按角色判断。

### 8.2 菜单与权限

**数据模型**：菜单树直接建在 spatie 的 `permissions` 表上，不另建菜单表。发布 spatie 迁移时直接加列：

| 列 | 说明 |
|---|---|
| `name`（spatie 原有） | 权限码，规则见 §6.2 |
| `parent_id` | 上级节点；根节点为 null |
| `type` | `catalog` / `menu` / `embedded` / `link` / `button`（§6.2） |
| `title` | 标题（前端 i18n key 或纯文本），输出为 `meta.title` |
| `path` | 路由路径；按钮为空 |
| `component` | 页面组件，只有 `menu` 使用 |
| `icon` | 图标，输出为 `meta.icon` |
| `sort` | 排序，输出为 `meta.order` |
| `status` | `Status` 枚举（§6.4） |
| `meta` | JSON，键名白名单：`activePath`、`activeIcon`、`keepAlive`、`affixTab`、`hideInMenu`、`hideChildrenInMenu`、`hideInBreadcrumb`、`hideInTab`、`badge`、`badgeType`、`badgeVariants`、`iframeSrc`、`link` |
| `is_builtin` | 是否内置节点，由同步命令维护 |

理由：角色授权直接就是 `role_has_permissions`，`syncPermissions()` 一行完成；节点与权限一一对应，删除节点时授权级联删除；spatie 的缓存序列化全部列，节点树可直接从 `PermissionRegistrar` 的缓存计算，不额外查库。

**节点规则**：

- 菜单节点自身的权限码就是“查看 / 列表”权限（`viewAny`、`view` 检查 `system.user`）；按钮只表示写操作：`create`、`update`、`delete`，以及 `status`、`password` 等专用动作。
- 类型层级：`catalog`、`menu`、`embedded`、`link` 只能挂在根或 `catalog` 下；`button` 只能挂在 `menu` 下。
- 停用向子孙级联生效：停用的节点及其子孙对所有人（含超管）失效。
- 其余保护规则见 §8.6。

**节点声明（扩展点）**：`App\Core\System\Contracts\MenuRegistry` 接口 + `Services` 中的单例实现，只保存启动时注册的静态声明。各模块在自己 Provider 的 `boot()` 中注册，System 自己的节点也这样注册。声明格式与 Vben 节点同构，`buttons` 键展开为 `<父 name>.<key>` 的按钮节点：

```php
// SystemServiceProvider
public function boot(MenuRegistry $menus): void
{
    $menus->register(require __DIR__.'/resources/menus.php');
}

// resources/menus.php
return [
    [
        'name' => 'system',
        'type' => 'catalog',
        'path' => '/system',
        'meta' => ['title' => 'system.title', 'icon' => 'lucide:settings', 'order' => 9000],
        'children' => [
            [
                'name' => 'system.user',
                'type' => 'menu',
                'path' => '/system/user',
                'component' => '/system/user/list',
                'meta' => ['title' => 'system.user.title', 'icon' => 'lucide:user'],
                'buttons' => [
                    'create' => 'common.create',          // → system.user.create
                    'update' => 'common.edit',
                    'delete' => 'common.delete',
                    'status' => 'system.user.changeStatus',
                    'password' => 'system.user.resetPassword',
                ],
            ],
        ],
    ],
];
```

**同步命令** `php artisan core:sync-permissions`：由 `DatabaseSeeder` 调用，部署时也执行（§11.4）。

1. 声明中有、数据库中没有的节点：新增并标记 `is_builtin`；上级按 `name` 查找，管理员移动过上级也能找到。数据库中已有同名的自定义节点时直接收编：标记 `is_builtin` 并按第 2 条修正结构性字段，在命令输出中列出。
2. 已有节点只修正结构性字段：`type`、`path`、`component`、`meta.link`、`meta.iframeSrc`；`title`、`icon`、`sort`、`status`、`parent_id` 与 `hide*` 系列以数据库为准，管理员的调整不被覆盖。
3. 从声明中移除的内置节点：取消 `is_builtin`，转为自定义节点并保留授权，在命令输出中列出；**不删除任何节点**。
4. 确保 `super-admin` 角色存在。
5. 最后清除 spatie 的权限缓存。

### 8.3 Policy 规范

- **标准方法齐全**：`viewAny`、`view`、`create`、`update`、`delete`、`restore`、`forceDelete`，以及批量的 `deleteAny`、`restoreAny`、`forceDeleteAny`。
- **关联操作单独授权**：如分配角色、挂载分类，各有对应方法。
- **标准写法**：先检查权限码 `$actor->can('<权限码>')`，再做业务保护，最后检查数据范围 `$record->isVisibleTo($actor)`；不通过时返回带提示文案的 `Response::deny()`（403，§6.1）。控制器只声明 `#[Authorize('update', 'user')]` 这类标准写法。

```php
public function delete(User $actor, User $user): Response
{
    if (! $actor->can('system.user.delete')) {
        return Response::deny();
    }

    if ($actor->is($user)) {
        return Response::deny(__('You cannot delete yourself.'));
    }

    if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
        return Response::deny(__('Only super admins can manage super admin users.'));
    }

    return $user->isVisibleTo($actor) ? Response::allow() : Response::deny();
}
```

- **默认拒绝**：测试强制要求每个模型都有 Policy 且标准方法齐全；每条受保护路由都有授权声明（§10.5）。
- 批量操作：默认检查一次批量方法；需要时逐条检查。

### 8.4 数据权限

- **范围提供者**：接口 `App\Core\System\Contracts\DataScopeProvider`，注册到 `DataScopeRegistry` 单例。Core 默认注册 `all`（全部）、`self`（仅本人，按 `created_by`）；模块可在自己的 Provider 中注册新的范围。每个角色选用一个范围（`roles.data_scope`），新建角色默认 `self`。

| 方法 | 说明 |
|---|---|
| `key()` | 范围标识，存入 `roles.data_scope` |
| `label()` | 显示名（多语言），作为角色表单的选项（`/system/roles/data-scopes`） |
| `constrain(Builder $query, User $user, Collection $roles)` | 给查询加条件；`$roles` 是该用户选用此范围的启用角色 |
| `contains(Model $record, User $user, Collection $roles)` | 单条判定，与 `constrain()` 同一规则 |

- **模型 trait `HasDataScope`** 提供 `scopeVisibleTo()`（查询范围 `visibleTo($user)`）与 `isVisibleTo($user)`（单条判定），Policy 复用同一规则。两者的规则相同：
  1. 超管，或任一启用角色的范围是 `all` → 不限制；
  2. 否则各启用角色的范围做 OR 并集；
  3. 没有启用角色，或范围 key 未登记 → 默认拒绝（查询加 `1=0`，单条判定为否）。
- 停用的角色，其权限与数据范围都不生效。
- 职责划分：**角色决定能做什么（功能权限），范围提供者决定能看哪些数据（数据权限）**。多角色取并集只会让可见范围变大，不会变得更细；更细的范围需要新增一个数据维度（即新增提供者）。
- **不内置部门等组织维度**。业务需要按部门、区域、门店等维度控制时，新增一个 `DataScopeProvider` 并在角色上选用即可，不改动脚手架代码。
- **所有查询入口**都必须经过数据权限：列表、详情、**下拉选项接口**、导出、统计、全局搜索。
- 禁止不带参数的 `withoutGlobalScopes()`（PHPStan 规则拦截），必须明确列出要去掉的作用域。
- 未来引入多租户时同样 fail-closed：拿不到租户即拒绝，而不是不过滤。

#### 需要部门数据权限时

本脚手架不内置部门、岗位。需要时按以下步骤自行加入，不改动现有的数据权限机制：

1. 在 `Modules` 下新建一个模块（如 `Organization`）：部门树模型与迁移（`departments` 表含 `parent_id`），用户表增加 `department_id`。
2. 需要按部门隔离的业务表增加 `department_id`，创建记录时自动填入当前用户的部门（模型事件或 Action 中处理）。
3. 实现 `DataScopeProvider`，提供“本部门”“本部门及下级”“自定义部门”三种范围（每种范围一个提供者，各有自己的 `key()`）；“自定义部门”通过角色与部门的关联表实现，`constrain()` / `contains()` 收到的 `$roles` 即用于查这张关联表。
4. 在模块的 Provider 中注册到 `DataScopeRegistry` 后，角色表单中自动出现这些范围选项。
5. 增加部门管理的后台接口与前端页面。
6. 补充越权测试：其他部门的数据在列表、详情、下拉选项、导出中均不可见。

岗位通常只是用户的一个属性或标签，与权限无关，按普通业务模块添加即可。

### 8.5 角色与用户

- `roles` 表在 spatie 原有列之外加：`title`（显示名）、`status`、`data_scope`、`remark`、`sort`。
- `roles.name` 存角色**标识**：`alpha_dash`、唯一，内置角色的标识不可修改；`/user/info` 的 `roles` 输出启用角色的标识。超管角色的标识为 `super-admin`（`Role` 模型常量）。
- 保存角色权限时，后端先把提交的权限码规范化：自动补齐每个节点的全部祖先节点，再 `syncPermissions()`。
- `users` 表加 `created_by`（可空），供“仅本人”数据范围使用，由 Action 写入。
- **防提权**：
  - 非超管授予角色的权限码，必须是自己有效权限码的子集；
  - 只有超管能授予或收回 `super-admin` 角色。

### 8.6 业务保护规则

按 §6.1 的分工：只看“操作者 + 目标记录本身”就能判定的，由 Policy 返回 **403**；取决于提交内容或全局状态的，由 Action 返回 **422**，括号内是错误挂的字段（删除类只给 `message`）。

**用户**

- 不能删除或停用自己 —— 403。
- 超管用户只有超管能编辑、重置密码、停用、删除 —— 403。
- 系统至少保留一个启用的超管：最后一个启用的超管不能删除（`message`）、不能停用（`status`）、不能去掉超管角色（`roles`）—— 422。
- 非超管授予或收回 `super-admin` 角色 —— 422（`roles`）。
- 停用走 `DisableUser`（吊销会话与 token，§7.3）；管理员重置密码后吊销对方的会话与 token（§7.4）。
- 用户名唯一，统一存为小写；重复时 422（`username`）。

**角色**

- `super-admin` 不能删除 —— 403；不能停用 —— 422（`status`）；标识不能改 —— 422（`name`）。权限隐含全部，角色表单中不显示权限树。
- 已分配给用户的角色不能删除 —— 422（`message`）。
- 授予的权限码超出自己的有效权限码 —— 422（`permissions`）。
- 停用角色的权限与数据范围都不生效。

**菜单节点**

- 内置节点不能删除 —— 403。
- 内置节点的 `name`、`type`、`path`、`component` 不可修改：前端只读，提交的值与库中不同 —— 422（对应字段）；`path`、`component` 只能由同步命令修改（§8.2）。
- `system.menu`、`system.menu.update` 两个节点及其祖先不能停用，防止把所有人锁在外面 —— 422（`status`）。
- 类型层级：`catalog` / `menu` / `embedded` / `link` 只能挂在根或 `catalog` 下，`button` 只能挂在 `menu` 下；修改类型后仍要能容纳现有的子节点 —— 422（`type` / `parent_id`）。
- 有子节点的节点不能删除 —— 422（`message`）。
- 非按钮节点的 `path` 唯一，且以 `/` 开头 —— 422（`path`）。
- 停用向子孙级联生效。

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
- 统计指标、复杂查询做成模块里独立的 Query 类。
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

**工具链兼容性（已实测）**：上表 7 项在 Pint、PHPStan / Larastan（max）、Rector、Pest（含架构测试解析）上全部可用；PHPStan 还能静态检出被丢弃的 `#[\NoDiscard]` 返回值，Rector 的 PHP 8.5 规则集会自动改写为 `new X()->m()`、`array_first()`。Pint 的格式化有两处不完善：

- 管道运算符：Pint 不会给 `|>` 补空格，约定**两侧手写空格、每段一行**；
- `clone(...)`：Pint 会改写为 `clone (...)`，接受这种写法。

Scramble 的兼容性在引入时另行验证。

### 10.3 常驻内存兼容规则

默认运行在 PHP-FPM 下也必须遵守，保证随时可切换到 Octane + FrankenPHP：

- 禁止用静态属性或单例保存请求相关数据；需要请求级状态时用 `#[Scoped]`、`Cache::memo()`、`Context`。
- 禁止在请求中修改 `config()`。
- 禁止 `exit()`、`die()`。
- 引入第三方包前确认其支持 Octane；有状态的服务登记到 Octane 的 `flush` 配置。
- 以上能自动检查的（静态属性、`exit`）加入架构测试。

### 10.4 代码生成

提供一套了解本目录结构的生成命令，统一放在 `core:` 命名空间下（`php artisan list core` 可查看全部）。官方 `make:*` 命令仍可使用，但需手写完整路径。

| 命令 | 生成 |
|---|---|
| `core:make-app {name} [--restricted]` | 入口目录（`app/Http/<Name>`）、ServiceProvider、路由文件、基础测试，并登记到 `config/core.php`、`bootstrap/providers.php`、`phpunit.xml`、`.env.example`；`--restricted` 生成受限入口（前缀带 `/v1`）。架构测试自动发现新入口 |
| `core:make-module {name}` | 业务模块（`app/Modules/<Name>`）+ 各受信任入口中的同名目录；`--crud --fields=... --soft-deletes` 生成完整增删改查 |
| `core:make-model {name} --module=` | 模型、迁移、工厂、Policy（标准方法齐全、默认拒绝），并在模块 Provider 中登记多态别名 |
| `core:make-migration` / `core:make-factory` / `core:make-data` | 为已有模型单独生成迁移、工厂；生成只读数据对象 |
| `core:make-action` / `core:make-query` / `core:make-policy` / `core:make-enum` | 模块中的对应类（`--module=`） |
| `core:make-contract {name} --module=` | 模块向受限入口导出的接口、只读数据对象与实现绑定 |
| `core:make-controller` / `core:make-request` / `core:make-resource` | 入口中的对应类（`--app= --module=`） |

- 全部继承官方 `Illuminate\Console\GeneratorCommand`：支持 `--force`、缺参数时交互提问，行为与官方 make 命令一致；失败时退出码为 1。
- 模板覆盖：项目中的 `stubs/core.<名称>.stub` 优先于命令自带模板，可用 `php artisan vendor:publish --tag=core-stubs` 发布后修改。
- 生成的入口默认要求登录：受信任入口使用会话（同 Admin），受限入口使用 token（无会话）。
- 路由不自动登记：生成控制器后命令会打印需要添加的路由；生成器本身是内核模块 `app/Core/Generator`，只在命令行下注册。
- 完整增删改查还会生成：权限节点（写入模块的 `resources/menus.php`，§8.2）、路由、操作日志文案、**授权测试**、模块 README。
- **新建文件必须使用生成器**，写入 `CLAUDE.md`；放错位置由架构测试拦截。

### 10.5 测试

- Pest；`tests/` 目录与 `app/` 对应。
- `tests/Pest.php` 提供统一辅助：以某权限登录、断言无权限返回 403、断言数据权限过滤。
- 每个接口至少覆盖：成功、无权限 403、数据权限之外 403/404、校验失败 422。
- 授权相关的约定测试（`tests/Arch`）：
  - 每条受保护路由都有授权声明；
  - 每个模型都有 Policy，且标准方法齐全（§8.3）；
  - 代码里用到的权限码都已在 `MenuRegistry` 中声明；
  - 不直接调用 spatie 的判定 API（§8.1）。
- 安全相关模块（认证、权限、数据权限）运行 Pest 变异测试。
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

- 后台接口（含 CSRF Cookie 接口）、`/storage/*`、`/up` → PHP-FPM；
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
3. `php artisan optimize`，然后 `php artisan core:sync-permissions` 同步菜单与权限节点（§8.2）；
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
| laravel/fortify 1.43 | **完整使用**：登录、两步验证、找回密码、邮箱验证、确认密码、个人资料（连带安装 laravel/passkeys 0.3，功能关闭） |
| laravel/sanctum 4.5 | SPA 会话认证 + token |
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
| larastan/larastan + phpstan | 静态分析，level max，不使用 baseline；`phpstan-deprecation-rules` |
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
| 菜单权限 | 建在 `permissions` 表上的菜单树（Vben 的 5 种节点类型）、`MenuRegistry` 与同步命令 `core:sync-permissions`、`Gate::before` 与 `PermissionRegistry`、按 Vben 路由结构输出菜单、权限码接口 | spatie/laravel-permission、Gate |
| 数据权限 | `DataScopeProvider` 接口与 `DataScopeRegistry`、默认“全部 / 仅本人”、模型 trait `HasDataScope` | Eloquent 查询范围、Policy |
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
| 代码生成器 | `core:make-*` 命令集（入口、模块、各层类）、定制 stub | 官方 `GeneratorCommand`、`stub:publish` |
| 架构与约定测试 | 边界、规范的自动检查 | Pest 架构测试、PHPStan |

## 14. 待决事项

| 事项 | 现状 |
|---|---|
| 附件 | 暂保留自建，需要缩略图时换 medialibrary |
| Passkeys | `laravel/passkeys` 仍为 0.x，暂缓 |
| 多租户 | 暂不做；做时采用 fail-closed 设计 |
| 后端管理 Caddy（域名、证书） | 暂不做 |
