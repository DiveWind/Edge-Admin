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
├── Models/            Eloquent 模型（与迁移、工厂、Policy 一起由 core:make-model 生成）
│   └── Concerns/      模型 trait（如 Core/System 的 HasDataScope，供各模块的模型使用）
├── Actions/           写操作用例（一个类一个用例，handle() 方法）
├── Policies/          授权规则（每个模型一个，标准方法齐全，§8.3）
├── Queries/           复杂查询、统计指标（只读）
├── Enums/             枚举（实现统一的 HasLabel / HasColor 接口）
├── Commands/          Artisan 命令（在模块 Provider 中 $this->commands([...]) 登记，§4.5）
├── Events/  Listeners/  Observers/  Jobs/  Notifications/
├── Rules/  Casts/
├── Contracts/  Data/  Services/   接口、只读数据对象（`<Name>Data`）与接口的实现：向受限入口开放的契约（§5.2），
│                                  以及 Core 提供的扩展点（如 Core/System 的 MenuRegistry、DataScopeProvider 与 MenuNodeData，§5.5）
├── resources/
│   ├── lang/          模块自己的语言包（命名空间 content::）
│   ├── views/         邮件、PDF、导出模板（不对外访问）
│   ├── menus.php      本模块的菜单与权限节点声明（§8.2）：返回节点数组，在 Provider 的 boot() 中
│   │                  `$menus->register(require __DIR__.'/resources/menus.php')`，由 core:sync-permissions 写入数据库
│   ├── dicts.php      本模块用到的字典类型与初始字典项（§6.7），注册到 DictRegistry，由 core:sync-dicts 写入
│   └── settings.php   本模块的系统参数分组（§6.7），注册到 SettingRegistry，由 core:sync-settings 写入
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
| 系统参数分组 | 在 Provider 的 `boot()` 中向 `App\Core\System\Contracts\SettingRegistry` 注册本模块的参数分组（`resources/settings.php`：分组标题、各参数的类型、默认值或作为默认值来源的 config 键、是否公开、额外校验规则）；由同步命令 `core:sync-settings` 写入，代码用 `Settings` 服务读取（§6.7）。一个分组只属于一个模块，键为 `<分组>.<参数名>`（如 `device.heartbeat_interval`） |
| 字典类型 | 在 Provider 的 `boot()` 中向 `App\Core\System\Contracts\DictRegistry` 声明本模块用到的字典类型与初始字典项（`resources/dicts.php`）；由同步命令 `core:sync-dicts` 写入，校验用 `new InDict('<类型>')`（§6.7）。业务模块的类型编码以模块名开头（如 `content.article_source`） |
| 日志 | 数据变更审计写入 activitylog 时使用本模块的日志名（模块名小写，如 `content`）；`operation`、`login` 是 Monitor 的保留日志名（§6.6）。日志的 `description` 存稳定的标识（i18n key、路由名、事件名），由前端翻译，后端不翻译、不存翻译后的文案 |
| 通知 | 定义本模块的通知类，统一出现在通知中心 |
| 异步导出 | 实现导出接口，即接入统一的异步导出、完成通知与下载 |
| 枚举选项 | 枚举实现 `HasLabel`（需要语义色时再实现 `HasColor`，§10.1），并在 Provider 的 `boot()` 中向 `App\Core\System\Services\EnumRegistry` 登记对外名称（**显式白名单**，未登记的不下发），即可由 `/options/enums` 下发给前端（§6.7）。业务模块的名称以模块名开头（如 `content.article_status`） |
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
  - **403**（带 `message`）：只看“操作者 + 目标记录本身”就能判定的，由 Policy 返回。包括没有权限码、超出数据范围、操作自己（删除 / 停用自己、在用户管理中重置自己的密码、强制下线自己当前的会话或 token）、非超管操作超管用户（含强制下线超管的会话与 token）、非超管修改或启停 `super-admin` 角色、删除内置节点、删除 `super-admin` 角色、删除内置的字典类型或字典项。
  - **422**（带字段错误 `errors`）：取决于提交内容或全局状态的，由 Action 抛出，错误挂在对应字段上。包括停用 `super-admin` 角色或生命线节点（`status`）、最后一个超管（停用挂在 `status`，去掉超管角色挂在 `roles`）、在修改用户时停用自己（`status`）、内置节点改结构性字段（`name` / `type` / `path` / `component` / `meta.iframeSrc` / `meta.link`）、节点类型层级不合法或成环、授予的权限码超出自身范围（`permissions`）、非超管授予或收回 `super-admin`、选用不可授予的数据范围（`data_scope`）、分配超出自身权限码或数据范围的角色（`roles`）、内置字典类型改 `code`、内置字典项改 `value`、参数值不符合类型或声明的校验规则（对应字段）、唯一性冲突。
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
| `name` | 节点的**权限码**，同时是 spatie 的 permission name 与 Vben 的路由 name，**全局唯一**。小写点分、以字母开头（`^[a-z][a-z0-9_-]*(\.[a-z0-9_-]+)*$`）：目录 `system`、菜单 `system.user`、按钮 `system.user.create`。权限码检查总是无参数（`can('system.user.create')`），与带模型参数的 Policy 调用不会混淆（§8.1） |
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
- **时间区间**由通用筛选 `App\Support\QueryBuilder\Filters\DateTimeRangeFilter` 实现（`DateTimeRangeFilter::allowed('created_at')`）：两端都是带时区偏移的 ISO 8601（dayjs 的 `format()`，如 `2026-11-19T08:00:00+08:00`，也接受 `Z` 与毫秒），任一端可空（`起,` / `,止`），两端都包含；比较前换算为库中的存储时区（应用时区，§10.1）。格式非法（缺逗号、没有时区偏移、日期不存在）或开始晚于结束 → 422，错误挂在 `filter.<字段>` 上（与其他筛选值的校验错误如 `filter.status` 一致）。查询字符串中的 `+` 要编码为 `%2B`（axios 默认如此）；未编码时被解析为空格，后端按 `+` 处理。
- 使用 `spatie/laravel-query-builder` 实现，**允许的筛选和排序字段显式白名单**；不在白名单内的参数返回 422（该包默认抛出 400，统一映射为 422）。`failOnUnknownFields` 只检查请求体，查询参数的白名单由该包负责。
- `pageSize` 超过上限返回 422，不自动截断。
- 返回 `{ items, total }`。
- 前端提供 `toListParams` 工具：把搜索表单的值包装成 `{ page, pageSize, filter: { … } }`，并去掉空值。

### 6.4 字段命名与数据格式

- **自有资源的字段一律 snake_case**（`parent_id`、`created_at`、`data_scope`）：校验错误的键、query-builder 的字段名、前端 `setFormErrors` 都要求**表单字段名 = 接口字段名**。只有 Vben 规定的结构使用 camelCase：`/user/info`、`/menu/all` 与菜单节点的 `meta.*`，以及分页参数 `pageSize`。
- **状态统一为字符串枚举 `enabled` / `disabled`**：通用枚举 `App\Core\System\Enums\Status`（实现 `HasLabel`），用户、角色、菜单节点共用。前端适配层 `CellSwitch` / `CellTag` 的默认值相应改为 `'enabled'` / `'disabled'`。
- **时间**输出带时区偏移的 ISO 8601（§10.1），前端表格列用 formatter 格式化。
- **id** 为自增整数（对外开放的入口使用 ULID，见 §5.7）。
- **不提供“名称 / 路径是否已存在”这类检查接口**：唯一性一律在保存时由 422 返回，前端用 `setFormErrors` 回填到对应字段。

### 6.5 系统管理接口

前缀均为 `/api/admin`（下表省略）。资源路径使用 `apiResource` 的复数形式；控制器只允许资源方法和 `__invoke`，所以非资源接口都是单动作控制器。“权限”列是 Policy 检查的权限码（§8.3）。

| 接口 | 说明 | 权限 |
|---|---|---|
| `GET /auth/codes` | 当前用户的权限码 `string[]`（§6.2） | 登录即可 |
| `GET /menu/all` | Vben 路由树（§6.2） | 登录即可 |
| `GET /system/users` | 分页 `{ items, total }`；筛选 `username`、`name`、`email`、`status`、`created_at`；含 `roles`（`[{ name, title }]`） | `system.user` |
| `GET /system/users/{user}` | 详情，含 `roles`（`[{ name, title }]`） | `system.user` |
| `POST /system/users` | 新增：`username`、`name`、`email`、`password`、`roles`（角色标识 `string[]`）、`status` | `system.user.create` |
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
| `GET /system/roles/data-scopes` | 数据范围选项 `[{ value, label }]`（§8.4），只含操作者可授予的范围（§8.5） | `system.role.create` 或 `system.role.update` |
| `GET /system/menus` | 整棵节点树（数组，**不分页**，是 §6.3 列表契约的例外） | `system.menu` |
| `GET /system/menus/{menu}` | 详情（不含 `children`） | `system.menu` |
| `POST /system/menus` | 新增自定义节点：`parent_id`、`type`、`name`、`path`、`component`、`status`、`meta.*` | `system.menu.create` |
| `PUT /system/menus/{menu}` | 修改：字段同新增 | `system.menu.update` |
| `DELETE /system/menus/{menu}` | 删除 | `system.menu.delete` |

- **菜单节点**（`/system/menus`，路由参数 `{menu}` 绑定 `Permission`）：
  - 输出字段：顶层 `id`、`parent_id`（根为 null）、`type`、`name`、`path`（按钮为 null）、`component`（只有 `menu` 有值，其余为 null；与 `/menu/all` 不同，不换成 `IFrameView`）、`status`、`is_builtin`，以及 `meta`（`title`、`icon`（可为 null）、`order`，和 `meta` 列中已设置的白名单键）。列表的每个节点另有 `children`（叶子为空数组）；详情与新增、修改的响应不含 `children`。
  - 列表：整棵树，含停用节点与按钮，同级按 `sort`、`id` 排序；一次查询取出全部节点后在内存中组装。
  - 写入字段：`type`、`name`、`meta.title` 必填；`parent_id`（已存在的节点）、`status` 可选——新增时默认根节点、`enabled`，修改时未提交表示不变；`path`、`component`、`meta.*` 按下表。`meta.title`（i18n key 或纯文本）、`meta.icon` 存入 `title`、`icon` 列，`meta.order`（整数，默认 0）存入 `sort` 列（不接受顶层 `sort`、`title`、`icon`），其余键按白名单（§8.2）存入 `meta` 列；`meta` 整体替换：未提交的 `icon` 为 null、`order` 为 0、其余键去掉，值为 null 的键视为未设置。未知的 `meta` 键 → 422（错误挂在 `meta.<键>`）。新增成功返回 201，`guard_name` 为 `web`，`is_builtin` 为 false。
  - 按类型的字段（**与类型不符的字段一律拒绝**：提交了非空值 → 422，挂在该字段上，不静默清空；null 视为未提交）：

    | `type` | `path` | `component` | `meta.iframeSrc` | `meta.link` | 其余 `meta` 白名单键 |
    |---|---|---|---|---|---|
    | `catalog` | 必填 | 不允许 | 不允许 | 不允许 | 可选 |
    | `menu` | 必填 | 必填 | 不允许 | 不允许 | 可选 |
    | `embedded` | 必填 | 不允许 | 必填 | 不允许 | 可选 |
    | `link` | 必填 | 不允许 | 不允许 | 必填 | 可选 |
    | `button` | 不允许 | 不允许 | 不允许 | 不允许 | 不允许（只有 `title`、`icon`、`order`） |

    `name` 的格式见 §6.2，全局唯一；`path` 以 `/` 开头，非按钮节点之间唯一（数据库唯一索引兜底并发）；`component` 以 `/` 开头（相对 `src/views`）；`meta.iframeSrc`、`meta.link` 只允许 `http` / `https` 网址；`meta.activePath` 以 `/` 开头；`keepAlive`、`affixTab`、`hide*` 为布尔值；`badgeType` 为 `dot` / `normal`；其余为字符串。
  - 内置节点的 `name`、`type`、`path`、`component`、`meta.iframeSrc`、`meta.link` 在前端只读（它们由同步命令维护）；提交的值与库中不同时返回 422（§8.6）。其余字段可以修改，再次同步不会覆盖。
  - 类型层级、成环、生命线节点、有子节点不能删等规则见 §8.6。写入后 spatie 的权限缓存随之清除，`/menu/all`、`/auth/codes` 立即按新数据计算。
  - Policy（`PermissionPolicy`）方法：`viewAny` / `view`（`system.menu`）、`create`、`update`、`delete`（删除内置节点 403）；节点不按数据范围过滤。
- **用户**的输出字段：`id`、`username`、`name`、`email`（可为 null）、`status`、`roles`、`created_at`、`updated_at`；不输出密码与两步验证的字段。
  - `roles` 在**读接口**（列表、详情，以及新增、修改、启停的响应）中输出为对象数组 `[{ name: 标识, title: 显示名 }]`（含停用的角色，按 `sort`、`id` 排序）：列表要显示角色名，而能看用户列表的人不一定有角色下拉（`/system/roles/options`）的权限。**写接口**的 `roles` 入参是角色标识 `string[]`，前端回填表单时取 `roles.map(r => r.name)`。
  - 列表：经过数据范围（`visibleTo`，§8.4）；筛选 `username`、`name`、`email`（模糊）、`status`（精确）、`created_at`（区间，§6.3）；排序 `id`、`username`、`created_at`，默认 `-id`。
  - 新增：`username`（必填，`alpha_dash`，最长 64，统一存为小写，转小写后唯一）、`name`（必填）、`email`（可空、唯一）、`password`（必填，规则同修改密码：`Password::defaults()`）、`roles`（已存在角色的标识，可省略）、`status`（默认 `enabled`）。`created_by` 写入操作者。成功返回 201。
  - 修改：字段同新增，不含 `password`（提交即 422）；未提交 `roles` 表示角色不变；提交的 `status` 与当前不同时按启用 / 停用处理（规则同状态接口）。
  - 重置密码：`{ password }`，规则同新增；成功返回 `data: null`，对方全部的会话与 token 失效。不能重置自己的密码（403，自己的密码在个人设置中验证当前密码后修改）。
  - 删除：同时吊销对方的会话与 token；对方创建的用户 `created_by` 置空。
  - Policy（`UserPolicy`）方法：`viewAny` / `view`（`system.user`）、`create`、`update`、`delete`、`changeStatus`（启用 / 停用，`system.user.status`）、`resetPassword`（`system.user.password`）；记录级方法都检查数据范围（`isVisibleTo`）。
- **角色**的输出字段：`id`、`name`（标识）、`title`、`status`、`data_scope`、`remark`、`sort`、`is_super_admin`（是否为 `super-admin` 角色，前端据此隐藏授权树、禁用删除与停用）、`created_at`、`updated_at`；详情与新增、修改的响应另含 `permissions`（权限码 `string[]`），列表不含。
  - 列表：筛选 `name`、`title`（模糊）、`status`（精确）；排序 `sort`、`id`、`created_at`，默认 `sort,id`。
  - 新增、修改：`name`、`title` 必填；`status`（默认 `enabled`）、`data_scope`（必须是已登记的范围，默认 `self`；非超管只能选自己可授予的范围，§8.5）、`remark`、`sort`（默认 0）、`permissions`（已登记的权限码）可选；修改时未提交 `permissions` 表示权限不变，`super-admin` 角色不能提交 `permissions`（422）。新增成功返回 201。
  - Policy（`RolePolicy`）方法：`viewAny` / `view`（`system.role`）、`create`、`update`、`delete`、`changeStatus`（启用 / 停用，`system.role.status`）、`viewFormOptions`（授权树与数据范围选项）、`viewOptions`（角色下拉）。
- **授权树**节点：`{ name, type, meta: { title, icon }, children }`，`icon` 可为 null，叶子的 `children` 为空数组；只含生效节点（本身及全部祖先都启用，含按钮与没有子节点的目录），同级按 `sort`、`id` 排序。
- 路由参数加格式约束（§9）：`{user}`、`{role}`、`{menu}` 等模型 id 只匹配数字，固定路径（`options`、`permission-tree`、`data-scopes`）不会被当成 id。
- 业务保护规则（不能删除自己、内置节点不能删等）见 §8.6。

### 6.6 监控接口

前缀与 §6.5 相同；内核模块 `app/Core/Monitor`，接口在 `app/Http/Admin/Monitor`。日志只读：**不提供新增、修改、删除、清空接口**，过期日志由调度清理（下文“日志的存储”）。

| 接口 | 说明 | 权限 |
|---|---|---|
| `GET /monitor/operation-logs` | 操作日志分页；不含 `route_parameters`、`payload` | `monitor.operation-log` |
| `GET /monitor/operation-logs/{operation_log}` | 详情，含 `route_parameters`、`payload` | `monitor.operation-log` |
| `GET /monitor/login-logs` | 登录日志分页 | `monitor.login-log` |
| `GET /monitor/login-logs/{login_log}` | 详情（字段同列表） | `monitor.login-log` |
| `GET /monitor/online/sessions` | 在线会话分页 | `monitor.online` |
| `DELETE /monitor/online/sessions/{session}` | 强制下线：删除该会话 | `monitor.online.logout` |
| `GET /monitor/online/tokens` | 有效的 token 分页 | `monitor.online` |
| `DELETE /monitor/online/tokens/{token}` | 吊销该 token | `monitor.online.logout` |
| `GET /monitor/schedules` | 定时任务分页 | `monitor.schedule` |
| `GET /monitor/schedules/{schedule}/logs` | 该任务的执行记录分页 | `monitor.schedule` |
| `POST /monitor/schedules/{schedule}/run` | 立即执行：派发到队列，立即返回（限流） | `monitor.schedule.run` |
| `GET /monitor/failed-jobs` | 失败任务分页 | `monitor.failed-job` |
| `GET /monitor/failed-jobs/{failed_job}` | 详情，另含 `payload` 摘要与 `trace`（已脱敏、截断） | `monitor.failed-job` |
| `POST /monitor/failed-jobs/{failed_job}/retry` | 重试（限流） | `monitor.failed-job.retry` |
| `POST /monitor/failed-jobs/retry` | 全部重试：`{ queue }`（可选，只重试该队列；限流） | `monitor.failed-job.retry` |
| `DELETE /monitor/failed-jobs/{failed_job}` | 删除 | `monitor.failed-job.delete` |
| `DELETE /monitor/failed-jobs` | 清空 | `monitor.failed-job.delete` |
| `GET /monitor/health` | 最近一次健康检查的结果 | `monitor.health` |
| `POST /monitor/health/check` | 立即检查：同步执行全部检查，返回结果（限流） | `monitor.health.check` |
| `GET /monitor/system-info` | 系统信息（只读） | `monitor.health` |

- **菜单节点**（`app/Core/Monitor/resources/menus.php`）：目录 `monitor`（`/monitor`，`lucide:monitor-cog`，`order` 9100，紧随 `system` 的 9000）下有菜单 `monitor.online`（`/monitor/online`，组件 `/monitor/online/list`，按钮 `monitor.online.logout`）、`monitor.operation-log`（`/monitor/operation-log`，组件 `/monitor/operation-log/list`）、`monitor.login-log`（`/monitor/login-log`，组件 `/monitor/login-log/list`）、`monitor.schedule`（`/monitor/schedule`，组件 `/monitor/schedule/list`，按钮 `monitor.schedule.run`）、`monitor.failed-job`（`/monitor/failed-job`，组件 `/monitor/failed-job/list`，按钮 `monitor.failed-job.retry`、`monitor.failed-job.delete`）、`monitor.health`（`/monitor/health`，组件 `/monitor/health/index`，按钮 `monitor.health.check`）。日志没有按钮。在线会话页用两个标签页分别展示会话与 token；健康检查与系统信息合并为一页（`monitor.health`）。
- 定时任务用 `schedule`（Laravel 的“任务调度”，`schedule:*` 命令）而不是 `job` 命名：`job` 在 Laravel 中指队列任务，与相邻的失败任务（`failed-job`）混淆。
- 路由参数加格式约束（§9）：`{operation_log}`、`{login_log}`、`{token}`、`{schedule}` 只匹配数字，`{session}` 只匹配 16 位字母与数字，`{failed_job}` 只匹配 UUID。

**日志的存储**

- 操作日志、登录日志与各模块的数据变更审计统一存在 spatie/laravel-activitylog 的 `activity_log` 表中，按日志名（`log_name`）区分：`operation`（操作日志）、`login`（登录日志）为 Monitor 保留，模块的审计使用模块自己的日志名（§5.5）。
- 模型：`App\Core\Monitor\Models\Activity` 继承 spatie 的模型（`config('activitylog.activity_model')` 指向它，多态别名 `activity`），写入时自动在 `properties.request_id` 中记下当前请求 ID（`Context` 的 `request_id`，§9；随队列任务传递），模块的审计日志同样带上，可与同一请求的操作日志关联。`OperationLog`、`LoginLog` 继承它，各带日志名的全局作用域：路由模型绑定、列表都只能取到本类日志（用操作日志的 id 请求登录日志详情为 404）。
- `causer` 是关联的后台用户（多态别名 `user`）；日志另存 `properties.username` 作为写入时的快照，用户改名、被删除后仍能看到是谁。
- 筛选的 `properties.*` 字段用 JSON 路径查询（查询构造器的 `properties->username`，三种数据库通用），没有索引；日志量很大时再按需加生成列与索引。
- 清理：调度每天执行 `activitylog:clean --force`（`routes/console.php`），删除早于 `activitylog.clean_after_days`（环境变量 `ACTIVITYLOG_CLEAN_AFTER_DAYS`，默认 365）天的全部日志（含模块的审计）。

**操作日志**（`log_name = operation`）

- 记录方式：后台入口的中间件 `App\Http\Admin\Middleware\RecordOperationLog`（admin 中间件组的第二个，紧随 `FormatResponse`），调用 Monitor 的 Action `LogOperation` 写入。规则：
  - 只记写请求：`POST`、`PUT`、`PATCH`、`DELETE`；
  - 只记已登录用户（请求结束时 `$request->user()` 是后台用户）的请求：未登录的写请求（登录失败、找回密码等）不记；
  - 认证请求只记登录日志，不记操作日志：路由名在 `config('core.monitor.operation_log.except_routes')` 中的不记，默认是登录（`login.store`）、登出（`logout`）、两步验证登录（`two-factor.login.store`）；个人设置中的修改资料、修改密码、开启 / 关闭两步验证等照常记录；
  - 无论成功失败都记，`status` 为最终的 HTTP 状态码（含 403、422、500）。
- 记录内容：`causer`（操作者）、`description`（路由名，如 `admin.system.users.store`、`user-password.update`；路由没有名称时为 `<方法> /<路由 URI>`）与 `properties`：`username`、`method`、`path`（实际请求路径，以 `/` 开头）、`route_parameters`（路由参数，取原始值）、`payload`（请求体，脱敏）、`status`、`duration_ms`（从进入本中间件到得到响应的毫秒数）、`ip_address`、`user_agent`（最长 500 字符）、`request_id`。
- `payload` 只取请求体（JSON 或表单字段，不含查询字符串；上传的文件不记录），在进入后续中间件之前读取，即提交的原始内容。
- **截断**（与脱敏一起在 Action 中完成）：字符串值超过 `config('core.monitor.operation_log.max_value_length')`（默认 1000）个字符的，保留前面部分并加上 `…(truncated)`，递归处理，`route_parameters` 同样适用；脱敏、截断后的 `payload` 编码为 JSON 仍超过 `max_payload_bytes`（默认 65536 字节）时，整体替换为 `{ "_truncated": true, "_bytes": 原始请求体的 JSON 字节数 }`。
- **脱敏**：键名（不区分大小写，支持 `*` 通配）与 `config('core.monitor.masked_keys')` 匹配的值整体替换为 `******`，递归处理嵌套对象与数组；默认 `*password*`（含 `password_confirmation`、`current_password`）、`*token*`、`*secret*`、`code`、`recovery_code`。`route_parameters` 同样脱敏。脱敏在 Action 中完成，任何调用方写入的操作日志都经过它。脱敏与截断的规则由 Monitor 的 `DataMasker` 实现，失败任务、定时任务的输出共用同一份规则与键名（下文“失败任务”）。
- **写入失败不影响响应**：在响应发送之后写入（`defer(...)->always()`，4xx / 5xx 也写），异常只 `report()`，不抛出。PHP-FPM 下在 `fastcgi_finish_request()` 之后执行；Octane 下由 worker 在发送响应后执行（框架的 `InvokeDeferredCallbacks`），不延迟响应，但该 worker 要等写完才接下一个请求。数据只在闭包中捕获，不放静态属性或单例。
- 文案：`description` 是路由名，由前端翻译（前端 locales 中以路由名为 key，找不到时直接显示路由名）；后端不翻译（§5.5）。
- 输出字段：`id`、`user_id`（`causer_id`，用户被删除后保留原值）、`username`、`description`、`method`、`path`、`status`、`duration_ms`、`ip_address`、`user_agent`、`request_id`、`created_at`；详情另含 `route_parameters` 与 `payload`（已脱敏）：通常是对象，为空时为 `[]`（响应包装按数组解码，无法区分空对象与空数组）。
- 列表：筛选 `user_id`、`method`（大写）、`status`、`request_id`（精确），`username`、`description`、`ip_address`（模糊），`created_at`（区间，§6.3）；排序 `id`、`created_at`，默认 `-id`。

**登录日志**（`log_name = login`，记录规则见 §7.3）

- 输出字段：`id`、`event`、`reason`、`via_remember`、`user_id`（事件涉及的账号；用户名不存在时为 null）、`username`、`ip_address`、`user_agent`、`request_id`、`created_at`。
  - `event`：`login`（登录成功）、`logout`（登出）、`failed`（登录失败）、`lockout`（登录过于频繁，被限流拒绝）、`password_reset`（通过找回密码重置了密码）。“结果”即 `event`：成功为 `login`，失败为 `failed`、`lockout`。
  - `via_remember`：只有 `login` 有——`true` 表示由“记住我”的 Cookie 自动恢复登录（会话已过期），`false` 表示提交用户名密码（或两步验证）登录；其余事件为 null。
  - `reason`：只有 `failed` 有值——`invalid_credentials`（用户名或密码错误）、`disabled`（账号已停用）、`two_factor`（动态码或恢复码错误）；其余为 null。
  - `username`：成功、登出、重置密码时为账号的用户名；失败、限流时为提交的用户名（转为小写，最长 255 字符）。**密码与重置密码的 token 在任何情况下都不记录。**
- 列表：筛选 `user_id`、`event`、`reason`、`request_id`（精确；`event`、`reason` 可用逗号传多个，如失败的登录 `filter[event]=failed,lockout`），`username`、`ip_address`（模糊），`created_at`（区间）；排序 `id`、`created_at`，默认 `-id`。

**在线会话与 token**（§7.3）

- **会话**：读取 database 会话驱动的会话表（`config('session.table')`，`config('session.connection')`），模型 `App\Core\Monitor\Models\Session`（只读，删除走 Action）。“在线”是 `user_id` 非空、`last_activity` 在 `session.lifetime` 分钟之内的会话。
  - 输出字段：`id`（**会话 ID 的前 16 位**：完整的会话 ID 就是登录凭据，不对外输出；前 16 位足以唯一定位，剩余部分仍无法猜测）、`user`（`{ id, username, name }`）、`ip_address`、`user_agent`、`last_activity`、`is_current`（是否为发起本次请求的会话）。不输出会话内容（`payload`）。
  - 列表：筛选 `user_id`（精确）、`username`、`ip_address`（模糊）；排序 `last_activity`，默认 `-last_activity`。
  - 会话驱动不是 `database` 时：会话表不是会话的来源，视同没有在线会话——列表为空（`{ items: [], total: 0 }`），下线返回 404；token 不受影响。生产使用 Redis 会话时，在线会话需另行记录（§7.3）。
- **token**：Sanctum 的 `personal_access_tokens` 中属于后台用户、仍然有效的 token：`expires_at` 为空或晚于现在，且配置了 `sanctum.expiration` 时创建于该分钟数之内（与 Sanctum 的校验一致）。
  - 输出字段：`id`、`name`、`abilities`、`user`（`{ id, username, name }`）、`last_used_at`、`expires_at`、`created_at`、`is_current`（是否为本次请求使用的 token）。不输出 token 本身（库中只有哈希）。
  - 列表：筛选 `user_id`（精确）、`username`、`name`（模糊）；排序 `id`、`last_used_at`、`created_at`，默认 `-id`。
- **强制下线**：删除会话记录或 token，成功返回 `data: null`，对方的下一次请求即为 401。会话与 token 规则相同，Policy 返回 403：
  - 没有 `monitor.online.logout`；
  - 自己**当前**的会话、本次请求使用的 token（应使用退出登录）；自己的其他会话与 token 可以下线；
  - 非超管下线超管用户的会话与 token（沿用用户管理的超管保护，§8.6）；
  - 所属用户超出数据范围。

**数据范围与授权**（§8.3、§8.4）

- 日志的数据范围**委托给 causer 用户**：可见 causer 是自己可见的用户（`User::visibleTo()`）的日志；不受数据范围限制的操作者（超管，或任一启用角色的范围是 `all`，`DataScopeRegistry::isUnrestrictedFor()`）可见全部日志，包括没有 causer 的（用户名不存在的登录失败）与操作者已被删除的。这样按部门等维度新增的数据范围（§8.4）只要能约束用户，就自动适用于日志。
- 会话与 token 按所属用户同样过滤（`whereHas` 到 `User::visibleTo()`）。列表经过数据范围；详情、下线超出范围 → 403。
- Policy：`OperationLogPolicy`、`LoginLogPolicy`（`viewAny` / `view`，其余方法拒绝）；`SessionPolicy`（`viewAny`、`delete`，`view` 同 `viewAny` 加数据范围，其余拒绝）；Sanctum 的 `PersonalAccessToken` 不在 `app/` 中，由 `MonitorServiceProvider` 用 `Gate::policy()` 绑定 `PersonalAccessTokenPolicy`（规则同 `SessionPolicy`）。基类 `Activity` 的 `ActivityPolicy` 全部拒绝：模块审计日志的查看接口以后由各模块按需提供。

**定时任务**（spatie/laravel-schedule-monitor）

- 调度只在 `routes/console.php` 中定义（§4.2）。schedule-monitor 把其中每个有名称的任务同步到 `monitored_scheduled_tasks` 表（`schedule-monitor:sync`：部署时执行，§11.4；`DatabaseSeeder` 也执行），并在 `schedule:run` 执行时监听框架的 `ScheduledTask*` 事件，更新上次开始、结束、失败、跳过的时间，写入执行记录 `monitored_scheduled_task_log_items`。
- 接口只读这两张表：HTTP 请求不加载 `routes/console.php`，**任务列表以最近一次同步为准**；修改调度后要重新同步（部署流程已包含）。同步时删除调度中已不存在的任务及其执行记录。
- 任务名（包的规则）：命令任务为去掉 `php artisan` 后的命令及参数（如 `activitylog:clean --force`），任务类为类名，闭包任务没有名称、不被监控（需要时用 `->monitorName('...')` 命名）。每分钟执行的健康检查与心跳（下文“健康检查”）用 `->doNotMonitor()` 排除：它们每天会产生数千条执行记录，运行状况由健康检查反映。
- 模型：`ScheduledTask`、`ScheduledTaskLog` 继承包的模型（`config/schedule-monitor.php` 的 `models` 指向它们，包写入时也使用它们），多态别名 `scheduled_task`、`scheduled_task_log`。写入全部由包完成，接口只读。
- 输出字段：`id`、`name`、`type`（`command` / `job` / `shell`）、`cron_expression`、`timezone`（调度的时区；未单独设置时为 null，按应用时区执行）、`last_started_at`、`last_finished_at`、`last_failed_at`、`last_skipped_at`（都可为 null）、`next_run_at`（按 cron 表达式与时区计算）、`is_running`（`last_started_at` 晚于上次结束与上次失败；进程被强制终止时会一直显示为运行中，直到下一次运行）。
- 列表：筛选 `name`（模糊）、`type`（精确）；排序 `id`、`name`、`last_started_at`，默认 `name`。
- 执行记录：输出 `id`、`type`（`starting` / `finished` / `failed` / `skipped`：每次运行一条开始，再加一条结束或失败）、`runtime`（秒）、`exit_code`、`memory`（字节）、`failure_message`、`output`（只有任务调用 `->storeOutputInDb()` 时才有，按失败任务的规则脱敏、截断）、`created_at`；不适用的字段为 null。筛选 `type`（精确，可用逗号传多个）、`created_at`（区间）；排序 `id`，默认 `-id`。
- 清理：调度每天执行 `model:prune --model=ScheduledTaskLog`（包的执行记录模型使用框架的 `MassPrunable`），删除早于 `schedule-monitor.delete_log_items_older_than_days`（`SCHEDULE_MONITOR_LOG_DAYS`，默认 30）天的执行记录。
- **立即执行**（`POST /monitor/schedules/{schedule}/run`）：
  - **不接受任何命令字符串**：路由只绑定库中已同步的任务；Action `RunScheduledTask` 派发队列任务 `ExecuteScheduledTask`（只带任务名）。队列 worker 是加载了调度定义的命令行进程，按与同步相同的规则（在当前环境运行、被监控、名称相同）在 `Schedule::events()` 中查找，**执行的永远是代码中定义的调度事件**，任务名只用作查找的键；找不到（同步后调度被删改）→ 该队列任务失败，出现在失败任务中。
  - 执行过程基于 `schedule:run`：派发 `ScheduledTaskStarting` → `$event->run()`（遵守 `withoutOverlapping()` 的互斥锁）→ 正常返回时 `ScheduledTaskFinished`（退出码非 0 时 schedule-monitor 记为 `failed`，带 `exit_code`、`runtime`），抛出异常时 `ScheduledTaskFailed`（带 `failure_message`）；异常只 `report()`。因此 schedule-monitor 照常记录：手动执行与定时执行在同一份执行记录中，手动执行一次只有“`starting` + 一条 `finished` 或 `failed`”。
  - 与 Laravel 13 的 `ScheduleRunCommand::runEvent()` 有一处不同：它在退出码非 0 时先派发 `ScheduledTaskFinished` 再派发 `ScheduledTaskFailed`，schedule-monitor 把两者都记为 `failed`，定时执行的这种失败会留下两条 `failed`（框架与包的行为，不改）。手动执行在退出码非 0 时不再派发 `ScheduledTaskFailed`，避免重复；代价是监听 `ScheduledTaskFailed` 的代码收不到这种手动执行的失败。谁点的执行见操作日志（`admin.monitor.schedules.run`）。与 `schedule:test` 相同，在前台执行（忽略 `runInBackground()`），不检查 `when()` / `skip()`、`onOneServer()` 与调度的暂停状态：手动执行就是明确要执行一次。
  - 只执行一次（`#[Tries(1)]`，失败不自动重试）；同一任务在排队或执行期间再次点击不会重复入队（`ShouldBeUnique`，按任务名加锁，最长 1 小时）。接口立即返回 `data: null`，执行结果在执行记录中查看。
  - 在 worker 中执行，受 worker 的 `--timeout`（默认 60 秒）限制；耗时长的任务（备份等）需调大 worker 的 timeout 与队列连接的 `retry_after`，或等待调度执行。队列驱动为 `sync` 时在请求中同步执行（只用于开发、测试）。

**失败任务**（框架的 `failed_jobs`）

- 读取失败任务表（`config('queue.failed.table')`，连接 `queue.failed.database`），模型 `FailedJob` 只读（多态别名 `failed_job`，接口中的 `id` 与路由参数是 `uuid`）。写入全部使用官方能力：重试执行 `queue:retry`（由它刷新 `retryUntil`、推回原连接与队列、删除记录并派发 `JobRetryRequested`；全部重试为 `queue:retry all`，指定队列时为 `--queue`）；删除、清空调用 `FailedJobProviderInterface` 的 `forget()`、`flush()`（即 `queue:forget`、`queue:flush` 的实现）。
- 失败任务驱动（`queue.failed.driver`）不是默认的 `database-uuids` 时（`file`、`dynamodb`、`null` 等）视同没有失败任务：列表为空，详情、单条重试与删除 404；全部重试、清空照常调用官方能力。
- 输出字段：`id`（UUID）、`connection`、`queue`、`name`（payload 的 `displayName`，通常是任务类名）、`exception`（异常的第一行：类名与消息）、`failed_at`；详情另含 `payload`（摘要）与 `trace`（异常第一行之后的调用栈，最多 `config('core.monitor.failed_job.max_trace_lines')` 行，默认 30，超出时末行为 `…(truncated)`）。
- **payload 摘要**：payload 按 JSON 解码，去掉 `data.command`（序列化的任务对象，可能已加密）后，其余键按操作日志的规则脱敏（`masked_keys`）、截断（`failed_job.max_value_length`，默认 1000 字符）。`data.command` 不反序列化（反序列化不可信数据有对象注入风险），序列化字符串也无法按键脱敏，因此不输出；`data.commandName` 保留。无法解码时为 `[]`。
- **异常的脱敏**：`exception` 与 `trace` 中 `键=值`、`键: 值`、`"键":"值"` 形式、键名与 `masked_keys` 匹配的值替换为 `******`；项目根目录的绝对路径去掉，只输出相对路径；`exception` 最长 `max_value_length` 个字符。SQL 绑定值等其他形式无法识别，异常中仍可能含业务数据：失败任务页只授予可信的运维角色。
- 列表：筛选 `queue`、`connection`（精确）、`exception`（模糊，在完整的异常文本中查找关键字）、`failed_at`（区间）；排序 `failed_at`，默认 `-failed_at`。
- 重试、全部重试、删除、清空成功都返回 `data: null`。全部重试在请求中逐条推回队列，积压很多时建议在命令行执行 `php artisan queue:retry all`。

**健康检查**（spatie/laravel-health）

- 检查项在 `MonitorServiceProvider` 中注册，阈值在 `config('core.monitor.health')`：

  | 检查（`name`） | 规则 |
  |---|---|
  | `Database` | 默认数据库连接可用 |
  | `DatabaseConnectionCount` | 只在默认连接为 MySQL / MariaDB / PostgreSQL 时注册：连接数超过 50 警告，超过 100 失败 |
  | `Cache` | 默认缓存可读写 |
  | `UsedDiskSpace` | 磁盘使用率超过 70% 警告，超过 90% 失败；依赖 `df` 命令，Windows 下默认不注册（§11.5，`CORE_HEALTH_DISK_SPACE`） |
  | `Queue` | 默认队列：心跳任务每分钟派发一次，最近 5 分钟内被 worker 执行过 |
  | `Schedule` | 调度心跳在 2 分钟内（`schedule:run` 每分钟执行） |
  | `DebugMode` | `APP_DEBUG` 为 false |
  | `Environment` | 环境为 `production` |
  | `OptimizedApp` | 配置、路由、事件已缓存（`php artisan optimize`） |

  开发环境中后三项失败是预期的。Redis、Horizon、备份等检查在引入对应组件时再加。
- 结果存储：包自带的 `CacheHealthResultStore`，只保留最近一次结果，不建表、不需要清理（缓存 `HEALTH_CACHE_STORE`，默认为默认缓存）。队列与调度的心跳同样存在缓存中，因此缓存必须是 Web、worker、调度进程共享的驱动（database、Redis、file），不能是 `array`。
- 调度（`routes/console.php`，都 `->doNotMonitor()`）：每分钟 `health:check --no-notification`（执行检查并保存结果）、`health:schedule-check-heartbeat`、`health:queue-check-heartbeat`。包的通知关闭（`HEALTH_NOTIFICATIONS_ENABLED`，默认 false）。
- **不开放包自带的 HTTP 端点**：不注册 `HealthCheckResultsController` / `HealthCheckJsonResultsController` 的路由，`health.oh_dear_endpoint.enabled` 为 false。给负载均衡用的存活探针使用 Laravel 自带的 `/up`（`bootstrap/app.php` 的 `health`）：只表示应用能启动并处理请求，不检查数据库等依赖，避免依赖抖动时整组实例被摘除。
- 输出：`{ finished_at, checks: [{ name, label, status, short_summary, notification_message, meta }] }`；尚无结果时 `finished_at` 为 null、`checks` 为 `[]`。`status`：`ok`、`warning`、`failed`、`crashed`（检查本身抛出异常）、`skipped`。`label`、`notification_message`、`short_summary` 是包的英文文案：前端按 `name` 翻译标签，消息原样显示。
- **立即检查**：Action `RunHealthChecks` 同步执行 `health:check --no-notification`（同一套检查，同时更新保存的结果），返回最新结果。耗时取决于检查项，通常不到 1 秒（数据库、缓存的连接超时除外），因此限流。

**系统信息**（`GET /monitor/system-info`，Query `SystemInfo`，每次请求实时读取，开销很小，不缓存）

```json
{
  "app": { "name", "environment", "debug", "maintenance", "timezone", "locale", "fallback_locale" },
  "runtime": { "php_version", "laravel_version", "server", "sapi", "os", "opcache", "memory_limit", "max_execution_time", "upload_max_filesize", "post_max_size" },
  "drivers": {
    "database": { "connection", "driver", "version" }, "cache": { "store", "driver" }, "queue": { "connection", "driver" },
    "session": { "driver" }, "filesystem": { "disk", "driver" }
  },
  "disk": { "total_bytes", "free_bytes", "used_bytes" },
  "server_time"
}
```

- `runtime.server`：`octane`（Octane worker，`$_SERVER['LARAVEL_OCTANE']`）、`fpm`（`fpm-fcgi`）、`cli`，其他为 PHP_SAPI 原值；`opcache` 是本进程是否启用了 OPcache（命令行下通常为 false）；`os` 只输出系统类型（`PHP_OS_FAMILY`），不输出主机名与内核版本。
- `disk` 是 `storage/` 所在磁盘，无法获取时（函数被禁用）为 null；`database.version` 取不到时为 null。
- **不输出**：密钥、密码、用户名、主机名与端口、`APP_URL`、任何路径、环境变量与 `.env` 的内容；只输出驱动与连接、存储的名称。

**授权、数据范围与限流**

- Policy：`ScheduledTaskPolicy`（`viewAny` / `view`：`monitor.schedule`；`run`：`monitor.schedule.run`）、`ScheduledTaskLogPolicy`（`viewAny` / `view`：`monitor.schedule`）、`FailedJobPolicy`（`viewAny` / `view`：`monitor.failed-job`；`retry`、`retryAny`：`monitor.failed-job.retry`；`delete`、`deleteAny`（清空）：`monitor.failed-job.delete`），其余方法拒绝。执行记录接口检查的是所属任务的 `view`。
- 健康检查与系统信息没有模型：控制器直接声明权限码（`#[Authorize('monitor.health')]`、`#[Authorize('monitor.health.check')]`），由 `Gate::before` 判定（§8.1）。
- **不按数据范围过滤**：定时任务、失败任务、健康状态与系统信息属于系统本身，不属于、也不“关于”某个用户（没有 `created_by`、causer 或所属用户可以委托，§8.4），能否访问只由权限码决定。失败任务的 payload 与异常可能含任意用户的数据，只授予可信的运维角色。
- **限流**：立即执行、重试（单条与全部）、立即检查在 admin 入口的总限流之外，另经过 `admin-ops` 限流器：每个用户每分钟 `config('core.apps.admin.ops_rate_limit')`（`CORE_ADMIN_OPS_RATE_LIMIT`，默认 10）次，超过返回 429。

### 6.7 字典、参数与选项接口

前缀与 §6.5 相同；内核模块 `app/Core/System`，接口在 `app/Http/Admin/System`。

| 接口 | 说明 | 权限 |
|---|---|---|
| `GET /options/dicts?types=a,b` | 字典选项：`{ 类型: [{ value, label, color }] }`（下文“选项下发”） | 登录即可 |
| `GET /options/enums?names=a,b` | 枚举选项：`{ 名称: [{ value, label }] }`（下文“枚举选项”） | 登录即可 |
| `GET /settings/public` | 公开参数：`{ 键: 值 }`（下文“公开参数”） | **无需登录** |
| `GET /system/dict-types` | 字典类型分页；筛选 `code`、`name`（模糊）、`status`（精确） | `system.dict` |
| `GET /system/dict-types/{dict_type}` | 详情 | `system.dict` |
| `POST /system/dict-types` | 新增：`code`、`name`、`status`、`remark` | `system.dict.create` |
| `PUT /system/dict-types/{dict_type}` | 修改：字段同新增 | `system.dict.update` |
| `DELETE /system/dict-types/{dict_type}` | 删除，连同其下的字典项 | `system.dict.delete` |
| `GET /system/dict-types/{dict_type}/items` | 该类型的字典项分页；筛选 `value`、`label`（模糊）、`status`（精确） | `system.dict` |
| `POST /system/dict-types/{dict_type}/items` | 新增字典项：`value`、`label`、`color`、`sort`、`status` | `system.dict.create` |
| `PUT /system/dict-items/{dict_item}` | 修改字典项：字段同新增 | `system.dict.update` |
| `DELETE /system/dict-items/{dict_item}` | 删除字典项 | `system.dict.delete` |
| `GET /system/settings` | 全部已声明的参数，按分组（数组，**不分页**，§6.3 的例外） | `system.setting` |
| `PUT /system/settings/{key}` | 修改单个参数：`{ value }` | `system.setting.update` |
| `PUT /system/setting-groups/{group}` | 按分组批量修改：`{ <参数名>: 值, … }` | `system.setting.update` |

**菜单节点**（`app/Core/System/resources/menus.php`，`system` 目录下）：`system.dict`（`/system/dict`，组件 `/system/dict/list`，`lucide:book-open-text`，`order` 40；按钮 `create`、`update`、`delete`，字典类型与字典项共用）、`system.setting`（`/system/setting`，组件 `/system/setting/index`，`lucide:sliders-horizontal`，`order` 50；按钮 `update`——参数不能新增、删除，没有对应按钮）。字典页是“类型列表 + 选中类型的字典项”，参数页是按分组的表单（与 `monitor.health` 一样用 `index`）。

**字典与枚举的分工**：代码要按值分支的固定集合（状态、类型、事件）用**枚举**（§10.1），值随代码发布、不能在后台改；管理员可以增删改、代码只校验“是否合法”的值列表（文章来源、客户行业）用**字典**。

**字典**

- 表：`dict_types`（`code` 唯一、`name`、`status`、`remark`、`is_builtin`）与 `dict_items`（`dict_type_id`、`value`、`label`、`color`、`sort`、`status`、`is_builtin`；同一类型内 `value` 唯一，数据库唯一索引兜底并发）。模型 `DictType`、`DictItem`（多态别名 `dict_type`、`dict_item`）。
- 格式：`code` 为小写点分的标识（`^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)*$`，最长 64，如 `content.article_source`）；`value` 为字符串（最长 64，接口中一律输出字符串）；`name`、`label` 与菜单的 `meta.title` 相同，存前端的 i18n key 或纯文本，**后端不翻译**（§6.2）；`color` 可空，给前端 Tag 用：antd 的预设色名（`success`、`processing`、`error`、`warning`、`default`、`blue` 等，小写字母与 `-`）或 `#RGB` / `#RRGGBB`；`sort` 为非负整数（默认 0）；`status` 为 `Status`（§6.4，默认 `enabled`）。
- 输出：类型 `id`、`code`、`name`、`status`、`remark`、`is_builtin`、`created_at`、`updated_at`；字典项 `id`、`dict_type_id`、`value`、`label`、`color`、`sort`、`status`、`is_builtin`、`created_at`、`updated_at`。
- 列表：类型筛选 `code`、`name`（模糊）、`status`（精确），排序 `id`、`code`、`created_at`，默认 `id`；字典项筛选 `value`、`label`（模糊）、`status`（精确），排序 `sort`、`id`、`value`，默认 `sort,id`。
- 写入：`code`、`name` / `value`、`label` 必填，其余可选（新增时取默认值，修改时未提交表示不变；`remark`、`color` 可提交 null 清空）。新增成功返回 201，`is_builtin` 为 false。字典项所属的类型由路径决定，不能修改。
- 内置（模块声明的，下文“声明与同步”）：内置类型**不能删除**（403）、`code` 不能修改（422，`code`）；内置字典项**不能删除**（403）、`value` 不能修改（422，`value`）。名称、备注、`label`、`color`、`sort`、`status` 都可以修改，再次同步不覆盖。代码依赖的只有类型的 `code` 与字典项的 `value`（`InDict`、业务判断），所以只保护它们。
- **删除类型时级联删除其下的字典项**（Action 先删字典项，外键 `cascadeOnDelete` 兜底），不做“有字典项不能删”：字典项完全从属于类型，没有类型就没有意义，逐条删除只增加操作；而内置字典项只会出现在内置类型下（同步只在声明的类型下收编、新增内置项，类型转为自定义时其内置项一并转为自定义），级联不会绕过“内置项不能删”。前端删除前提示会一并删除字典项。
- 没有单独的启用 / 停用接口：`status` 在修改接口中提交。
- Policy：`DictTypePolicy`、`DictItemPolicy`：`viewAny` / `view`（`system.dict`）、`create`、`update`、`delete`（内置的 403，超管也一样），其余方法拒绝；不按数据范围过滤（字典属于系统本身，§8.4）。

**选项下发**（`GET /options/dicts?types=a,b`）

- `types` 必填，逗号分隔的类型编码（最多 50 个，格式同 `code`，重复的去掉），否则 422（`types`）。响应是以请求的类型为键的对象，按请求顺序；每个类型的值是 `[{ value, label, color }]`：只含**启用类型下的启用项**，按 `sort`、`id` 排序，`color` 没有设置时为 null。不存在或已停用的类型返回 `[]`，不报错：类型是后台可删改的数据，页面不应因此报错。
- **登录即可访问**（`LOGIN_ONLY_ROUTES`），不需要权限码：字典是各模块表单共用的参考数据（不是业务数据），按权限码控制就要给每个用到下拉的角色都授权字典，授权会失控。默认拒绝仍然成立：未登录 401（停用账号 401）；只输出启用项的 `value`、`label`、`color`，不输出 `remark`、`is_builtin`、停用项等管理字段；**敏感的值列表不要做成字典**——放在模块自己的表中，由模块的接口按权限码与数据范围下发（§8.4）。
- 缓存：Service `Dictionary` 把全部启用类型的启用项整体缓存（`Cache::rememberForever('core:dicts')`，经 `Cache::memo()` 读取，同一请求内只访问一次缓存存储）；字典类型、字典项的任何写入（模型的 saved / deleted，**事务提交后**）与同步命令都清除缓存，下一次读取重新加载。`Dictionary` 不保存任何状态（每次调用 `Cache::memo()`，它是 scoped 绑定），常驻内存安全。
- 校验：`App\Core\System\Rules\InDict`——`'source' => ['required', new InDict('content.article_source')]`：值必须是该类型（启用）下启用项的 `value`（接受字符串或整数，按字符串比较），否则为 `validation.in` 的错误；类型不存在或已停用时一律不通过。读的是同一份缓存。

**系统参数**

- 用途：简单的、后台可调的业务开关与参数（§5.8）。**部署级的配置留在 config 与环境变量**（入口与前缀、API 限流阈值、分页上限、前端地址、脱敏键名、日志保留天数等），改动需重新部署；只有“运行时由管理员调整”的才进系统参数。
- 声明（`resources/settings.php`，注册到 `SettingRegistry`，单例，只保存启动时注册的静态声明）：

  ```php
  return [
      'auth' => [                                     // 分组：小写标识，一个分组只属于一个模块
          'title' => 'system.setting.auth.title',     // i18n key 或纯文本，后端不翻译
          'order' => 20,                              // 分组的顺序
          'settings' => [
              'login_rate_limit' => [                 // 键为 auth.login_rate_limit
                  'type' => 'int',                    // SettingType：string、text、int、float、bool、json
                  'name' => 'system.setting.auth.loginRateLimit',
                  'remark' => 'system.setting.auth.loginRateLimitRemark',   // 可选
                  'config' => 'core.auth.login_rate_limit',  // 默认值来源：config 键；或用 'default' => 值，二者选一
                  'public' => false,                  // 是否出现在公开参数接口中，默认 false
                  'rules' => ['min:1', 'max:1000'],   // 额外校验规则，可选
              ],
              'default_level' => [
                  'type' => 'string',
                  'name' => 'system.setting.auth.defaultLevel',
                  'default' => 'basic',
                  'dict' => 'customer_level',         // 引用字典（只用于 string、int）：值必须是该字典的启用项
              ],
          ],
      ],
  ];
  ```

  注册时校验，声明有误抛出 `InvalidArgumentException`：分组与参数名为小写标识（`^[a-z][a-z0-9_]*$`），键 `<分组>.<参数名>` 不超过 128 个字符；分组不能重复注册；`type` 合法；`default` 与 `config` 恰好一个，`default` 的类型与 `type` 一致；`dict` 为字典类型的编码，且 `type` 是 `string` 或 `int`。
- **引用字典**（`dict`）：值的可选范围由字典决定——校验自动追加 `new InDict('<code>')`（只接受启用项的 `value`，`int` 按字符串比较），管理接口输出 `dict`，前端据此调用 `/options/dicts` 渲染下拉。引用的字典类型在库中不存在（或已停用）时任何非 null 的值都校验失败，这是预期行为（null 仍可恢复默认值）；默认值不检查是否在字典中。不另设静态的可选值列表，可选值一律用字典表达。
- 类型（`SettingType`）：`string`（单行，最长 255）、`text`（多行，最长 65535）、`int`、`float`、`bool`、`json`（数组或对象）。决定接口的校验规则与读取时的类型；声明的 `rules` 追加在类型规则之后。
- 表 `settings`：`key`（唯一）、`group`、`type`、`name`、`remark`、`value`（JSON 编码，可空）、`is_public`、`is_builtin`。**`value` 为 null 表示管理员没有改过**，读取时使用声明的默认值；改过才存值。因此“管理员改过的值”与“默认值”始终可区分：同步不写 `value`，默认值（含 config 中的环境变量）的变化对没改过的参数立即生效。
- 读取（给代码用）：注入或 `resolve()` 服务 `App\Core\System\Services\Settings`，**不提供全局 helper 与 facade**：`get($key)`，以及按声明类型返回的 `string()`、`integer()`、`float()`、`boolean()`、`array()`（声明类型不符时抛出 `LogicException`）。读取未声明的键抛出 `InvalidArgumentException`（参数必须先声明）。值的来源：库中的值（类型与声明一致时）→ 否则声明的默认值（`default`，或读取 `config`）。整张表的值整体缓存（`Cache::rememberForever('core:settings')`，经 `Cache::memo()`），写入（事务提交后）与同步命令清除缓存。服务不保存状态，常驻内存安全。
- 管理接口：
  - `GET /system/settings`：`[{ group, title, settings: [...] }]`，分组按 `order`、注册顺序，参数按声明顺序；参数输出 `key`、`name`、`remark`、`type`、`value`（当前生效的值）、`default`（声明的默认值）、`is_customized`（库中有管理员改过的值）、`is_public`、`dict`（引用的字典类型，没有时为 null）。只列出已声明的参数。
  - `PUT /system/settings/{key}`：`{ value }`（必须提交，可为 null）；`PUT /system/setting-groups/{group}`：请求体的键是该分组的参数名（不带分组前缀，校验错误挂在参数名上，前端表单字段名 = 参数名），只修改提交的参数，未声明的键 → 422。**`value` 为 null 表示恢复默认值**（空字符串经框架的 `ConvertEmptyStringsToNull` 同样视为 null，因此字符串参数不能存空字符串，除非默认值就是空字符串）。按类型校验（`int` 为整数，`bool` 接受 `true` / `false` / `0` / `1`，`json` 为数组或对象）并追加声明的 `rules`（引用字典时再加 `InDict`），不通过 → 422（单个挂在 `value`，分组挂在参数名）；保存前按类型转换（`"10"` → `10`，`"1"` → `true`）。未声明的键或分组 → 404。响应为修改后的参数 / 分组（格式同列表）。
  - **只能修改已声明的参数，接口不能新增、删除参数**：参数是给代码读的，代码不读的参数没有意义；需要新参数就在模块中声明（随代码发布，类型、默认值、校验一起评审）。需要后台自由增减的键值，用字典或模块自己的表。
  - Policy：`SettingPolicy`：`viewAny` / `view`（`system.setting`）、`update`（`system.setting.update`），`create`、`delete` 等一律拒绝。
- 声明与同步见下文。

**公开参数**（`GET /settings/public`）

- **不需要登录**（在 `auth:sanctum` 分组之外）：前端在登录前读取站点名称、登录页文案等。响应 `{ 键: 值 }`，只含**声明为 `public` 的参数**（以代码中的声明为准，不读库中的 `is_public`，库中的标记改了也不会泄露）；没有公开参数时为 `[]`（§6.6 的数组解码说明）。
- 限流：admin 入口的总限流（未登录按 IP，`core.apps.admin.rate_limit`）；数据来自缓存，开销很小，不另设限流器。
- 缓存头：使用框架默认的 `Cache-Control: no-cache, private`：管理员修改后立即生效，且响应可能带会话 Cookie（不能被共享缓存）；服务端已有缓存，重复请求不查库。
- System 声明的公开参数：`site.name`（站点名称，默认 `config('app.name')`）、`site.login_notice`（登录页提示文案，`text`，默认空字符串）。

**接入现有配置**：登录与两步验证的限流次数改由系统参数 `auth.login_rate_limit` 控制（§7.2），`config('core.auth.login_rate_limit')`（`CORE_LOGIN_RATE_LIMIT`）是它的默认值来源。其余 `config/core.php` 的项都是部署级配置，留在 config：API 与运维动作的限流阈值（§5.8 列为部署级）、分页、`home_path`（依赖前端路由）、前端地址与重置密码路径、脱敏键名与日志长度、健康检查阈值；日志保留天数由包的命令读取 config，也留在 config。

**枚举选项**（`GET /options/enums?names=a,b`）

- `names` 必填，逗号分隔的对外名称；响应是以名称为键的对象，按请求顺序，每个枚举的值是 `[{ value, label, color? }]`（按 `cases()` 顺序），`label` 是 `HasLabel::label()`，**按 `Accept-Language` 翻译**（与提示文案相同，§6.1）；枚举另外实现了 `HasColor` 时每个选项有 `color`（规则同字典项的 `color`），没有实现时不输出该键。
- **显式白名单**：只下发在 `EnumRegistry` 中登记过对外名称的枚举，名称不在登记中 → 422（`names`），不按类名反射。登记时校验：名称为小写点分标识、不重复，类是实现 `HasLabel` 的 backed enum；实现了 `HasColor` 的，每个 case 的颜色都合法。模块在 Provider 的 `boot()` 中登记：`$enums->register(['content.article_status' => ArticleStatus::class])`。
- 已登记：`status`（`Status`，有颜色：`enabled` 为 `success`、`disabled` 为 `error`）、`permission_type`（`PermissionType`）、`setting_type`（`SettingType`）、`login_event`（`LoginEvent`，有颜色：`login` 为 `success`、`logout` 为 `default`、`failed` 为 `error`、`lockout` 为 `warning`、`password_reset` 为 `processing`）、`login_failure_reason`（`LoginFailureReason`）。
- **登录即可访问**：枚举是代码中的固定集合，不是业务数据；不开放给未登录请求（登录页不需要），默认拒绝。不缓存（直接由代码计算）。

**声明与同步**

字典与参数的声明与菜单节点同构：Core 提供接口（`Contracts/DictRegistry`、`Contracts/SettingRegistry`）与 `Services` 中的单例实现，只保存启动时注册的静态声明；各模块在 Provider 的 `boot()` 中 `register(require __DIR__.'/resources/dicts.php')`（`settings.php` 同理）；同步命令由 `core:sync` 依次执行（`DatabaseSeeder` 调用，部署时也执行，§11.4）。都幂等（再次执行没有变更），在一个事务中，结束时清除缓存，在命令输出中列出每一类变更。

```php
// resources/dicts.php
return [
    [
        'code' => 'content.article_source',
        'name' => 'content.dict.articleSource',       // i18n key 或纯文本
        'remark' => null,                             // 可选
        'items' => [
            ['value' => 'original', 'label' => 'content.dict.articleSource.original', 'color' => 'success'],
            ['value' => 'reprint', 'label' => 'content.dict.articleSource.reprint'],   // color、sort 可选；sort 默认为声明中的序号
        ],
    ],
];
```

`php artisan core:sync-dicts`：

1. 声明中有、库中没有的类型：新增并标记 `is_builtin`（启用），连同声明的字典项（内置、启用）；库中已有同 `code` 的自定义类型时收编（标记 `is_builtin`）。
2. 已有类型下：声明中有、库中没有的字典项新增为内置项；同 `value` 的自定义项收编。**结构性字段只有标识**（类型的 `code`、字典项的 `value` 与所属类型），已有的类型与字典项的名称、备注、`label`、`color`、`sort`、`status` 以库为准，管理员的调整不被覆盖。
3. 从声明中移除的内置字典项、内置类型（连同其全部内置项）：取消 `is_builtin`，转为自定义，**不删除**。

`php artisan core:sync-settings`：

1. 声明中有、库中没有的参数：新增（`is_builtin`，`value` 为 null，即使用默认值）。
2. 已有参数修正结构性字段：`type`、`group`、`name`、`remark`、`is_public`（都由声明决定，接口不能修改），未标记的收编为内置；`value` 不动。声明修改了类型、库中的值已不符合新类型时，把 `value` 置为 null（恢复默认值）并在输出中列出。
3. 从声明中移除的参数：取消 `is_builtin` 并取消 `is_public`，保留 `value`，**不删除**；它们不再出现在列表与公开接口中，代码也读不到（未声明），重新声明时收编并沿用原来的值。这样留在库中的废弃参数无害，不提供清理命令（需要时用迁移删除）。

`php artisan core:sync`：依次执行 `core:sync-permissions`、`core:sync-dicts`、`core:sync-settings`，任何一个失败即以它的非 0 退出码结束，后面的不再执行；`DatabaseSeeder` 与部署流程（§11.4）都用它，三个单独的命令保留。它**不包含** `schedule-monitor:sync`：那是 Monitor 所用的包的命令（System 不依赖 Monitor），同步的是调度定义而不是模块的声明，并且会删除调度中已不存在的任务，在部署流程中单独执行。

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
| 登录、登出、登录限流 | 登录字段配置为 `username`；在 `Fortify::authenticateUsing()` 中拒绝已禁用账号；限流次数由系统参数控制：`auth.login_rate_limit`（同一“用户名 + IP”每分钟的登录次数，两步验证按同一次数；默认值来自 `config('core.auth.login_rate_limit')`，环境变量 `CORE_LOGIN_RATE_LIMIT`，§6.7），限流器每次请求时读取，后台修改后立即生效 |
| 两步验证 | TOTP 动态码 + 恢复码，开启后需确认才生效 |
| 找回密码、重置密码 | 邮件链接重置 |
| 邮箱验证 | 按需开启 |
| 敏感操作前确认密码 | 如关闭两步验证、修改安全设置 |
| 修改个人资料、修改密码 | 修改密码后让其他设备下线：database 会话驱动下直接删除该用户的其他会话并吊销全部 token；其他驱动由 Sanctum 的 `AuthenticateSession` 在下次请求时注销 |
| 注册 | **关闭** |
| Passkeys | Fortify 已集成；依赖的 `laravel/passkeys` 仍为 0.x，**暂不开启** |

需要定制响应时，绑定 Fortify 的响应契约类，不改 Fortify 本身。

### 7.3 认证相关的自建部分

- **登录日志**：监听 Laravel 与 Fortify 的标准认证事件，由 Monitor 的 Action `LogLogin` 写入 activitylog 的 `login` 日志（接口与字段见 §6.6）：

  | 事件 | `event` | `reason` | 账号（`causer`） |
  |---|---|---|---|
  | `Illuminate\Auth\Events\Login`（含“记住我”自动登录；开启两步验证时在验证通过后才触发） | `login` | — | 登录的用户 |
  | `Illuminate\Auth\Events\Logout` | `logout` | — | 登出的用户 |
  | `Illuminate\Auth\Events\Failed` | `failed` | 账号已停用为 `disabled`，其余为 `invalid_credentials` | 事件带的用户，没有时按提交的用户名查找 |
  | `Laravel\Fortify\Events\TwoFactorAuthenticationFailed` | `failed` | `two_factor` | 待验证的用户 |
  | `Illuminate\Auth\Events\Lockout` | `lockout` | — | 按提交的用户名查找 |
  | `Illuminate\Auth\Events\PasswordReset`（Fortify 的 `POST /reset-password`） | `password_reset` | — | 被重置密码的用户 |

  - 只记录后台用户（`User`）的事件；同时记下 IP、User-Agent、请求 ID。
  - “记住我”：`Login` 事件的 `remember` 在勾选“记住我”登录与用 recaller Cookie 恢复登录时都为 true，无法区分；监听按事件中的守卫名取守卫，用 `SessionGuard::viaRemember()`（恢复登录时先置位再派发 `Login`）写入 `properties.via_remember`；没有 `viaRemember()` 的守卫（token）为 false。
  - `PasswordReset` 只带用户：不记录新密码与重置 token。管理员在用户管理中重置密码不派发它（记在操作日志中）。
  - **不记录密码**：`Failed` 事件的 `credentials` 中含提交的密码，只取其中的用户名；不记录 `credentials` 本身与请求体。
  - Fortify 在使用命名限流器（`fortify.limiters.login`）时不派发 `Lockout`，由登录限流器的拒绝响应（`SystemServiceProvider`）派发框架标准的 `Lockout` 事件。
  - 停用账号用正确的密码登录时，`AuthenticateUser` 在返回 422 前派发 `Failed`（带该用户，`credentials` 只有用户名），否则这次尝试不会触发任何事件。
  - 不记录：两步验证的质询（通过后有 `login`，失败有 `failed`）、确认密码、发送找回密码邮件。
  - 写入失败只 `report()`，不影响登录本身。
- **在线会话与强制下线**：会话使用 `database` 驱动（生产可用 Redis 并另行记录），列表读取会话表（`Session` 模型，只对外输出会话 ID 的前 16 位）；强制下线即删除会话；设备 / App 的 token 一并展示与吊销。非 database 驱动时会话列表为空。接口与规则见 §6.6。
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
  - 模型 `App\Core\System\Models\Permission`、`Role` 继承 spatie 的模型，在 `config/permission.php` 中指向它们；guard 统一为 `web`（用设备 token 认证的也是同一个 `User`）：`User::guardName()` 固定返回 `web`，新建角色时显式写入 `guard_name`，否则 spatie 会取当前请求的 guard（`auth:sanctum` 下为 `sanctum`）。
  - `permission.register_permission_check_method = false`（不注册 spatie 自带的 Gate 检查）；`permission.register_octane_reset_listener = true`。
- **权限判定由自建的 `Gate::before` 完成**（在 `SystemServiceProvider` 中注册），只处理权限码：
  - **无参数的调用**（`can('system.user.create')`、`#[Authorize('system.user')]`），且 ability 是**已登记的权限码**（以是否登记为准，不看格式；顶级目录 `system` 也是权限码）：在用户的有效权限码中 → `true`，否则 → `false`。节点或其任一祖先停用时，超管也得到 `false`。
  - **带参数的调用**一律返回 `null`，交给 Policy：Policy 调用总带模型或类（`can('update', $model)`、`can('create', User::class)`、`#[Authorize('update', 'user')]`），权限码检查总是无参数。因此即使有节点恰好叫 `update`、`export`，也不会绕过 Policy。
  - 其他 ability（未登记的名称，如无参数的 `publish`）→ 返回 `null`，交给 Gate 中定义的规则。因此超管只对权限码直接放行，同样受 Policy 中记录级规则的约束（不能删除自己、不能删除内置节点等）。
- **有效权限码**由 `#[Scoped]` 的 `PermissionRegistry` 计算，每个请求只算一次。`Gate::before`、`/auth/codes`、`/menu/all` 都使用它，三处口径一致：
  - 普通用户：节点本身及全部祖先都**启用**，且都在用户**启用角色**的权限码中，该节点才生效；
  - 超管：全部启用节点（节点本身及全部祖先都启用）。
  - 保存角色权限时后端会自动补齐祖先节点（§8.5），正常情况下不会出现“有子无父”的授权。
- **禁止**直接使用 spatie 的判定 API：`hasRole()`、`hasAnyRole()`、`hasPermissionTo()`、`checkPermissionTo()`、路由宏 `->role()` / `->permission()`、`role:` / `permission:` 中间件等，它们绕过停用逻辑。由 PHPStan `disallowed-calls` 加架构测试强制；所有判定一律走 `can()` / Gate / Policy，除“是否超管”外不按角色判断。

### 8.2 菜单与权限

**数据模型**：菜单树直接建在 spatie 的 `permissions` 表上，不另建菜单表。发布 spatie 迁移时直接加列：

| 列 | 说明 |
|---|---|
| `name`（spatie 原有） | 权限码，规则见 §6.2（格式由 `Permission::isValidName()` 判定，模块声明与菜单管理共用） |
| `parent_id` | 上级节点；根节点为 null |
| `type` | `catalog` / `menu` / `embedded` / `link` / `button`（§6.2） |
| `title` | 标题（前端 i18n key 或纯文本），输出为 `meta.title` |
| `path` | 路由路径；按钮为空；唯一索引（null 不参与） |
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

**同步命令** `php artisan core:sync-permissions`：由 `core:sync` 调用（`DatabaseSeeder` 与部署时执行，§6.7、§11.4），也可单独执行。

1. 声明中有、数据库中没有的节点：新增并标记 `is_builtin`；上级按 `name` 查找，管理员移动过上级也能找到。数据库中已有同名的自定义节点时直接收编：标记 `is_builtin` 并按第 2 条修正结构性字段，在命令输出中列出。
2. 已有节点只修正结构性字段：`type`、`path`、`component`、`meta.link`、`meta.iframeSrc`；`title`、`icon`、`sort`、`status`、`parent_id` 与 `hide*` 系列以数据库为准，管理员的调整不被覆盖。
3. 从声明中移除的内置节点：取消 `is_builtin`，转为自定义节点并保留授权，在命令输出中列出；**不删除任何节点**。
4. 确保 `super-admin` 角色存在。
5. 最后清除 spatie 的权限缓存。
6. `path` 唯一：写入前先检查，声明节点的 `path` 已被另一个不在声明中的节点（自定义节点、已转为自定义的节点）占用时，命令列出每个冲突（声明节点、`path`、占用它的节点）与处理建议（在菜单管理中改掉或删除占用的节点后重跑），以非 0 退出码结束，**不写入任何数据**。整次同步在一个事务中，要么全部成功，要么全部回滚；声明之间互换 `path` 正常完成。

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

- **范围提供者**：接口 `App\Core\System\Contracts\DataScopeProvider`，注册到 `DataScopeRegistry` 单例。Core 默认注册 `all`（全部）、`self`（仅本人）；模块可在自己的 Provider 中注册新的范围。每个角色选用一个范围（`roles.data_scope`），新建角色默认 `self`。
- **“仅本人”的语义**：记录“属于”用户时可见。“属于”由模型声明：`HasDataScope::dataScopeOwnerColumns()` 返回归属列，任一列的值等于用户 id 即属于该用户（多列为 OR）；默认只有 `created_by`，模型可覆盖。`User` 声明为 `created_by`、`id`——“仅本人”的操作者在用户管理中能看到**自己和自己创建的用户**。`SelfDataScope` 的 `constrain()` 与 `contains()` 读取同一组列，保持同一规则。本人可见不等于能删除 / 停用自己，后者仍由 Policy 拒绝（403，§8.6）。

| 方法 | 说明 |
|---|---|
| `key()` | 范围标识，存入 `roles.data_scope` |
| `label()` | 显示名（多语言），作为角色表单的选项（`/system/roles/data-scopes`） |
| `constrain(Builder $query, User $user, Collection $roles)` | 给查询加条件；`$roles` 是该用户选用此范围的启用角色 |
| `contains(Model $record, User $user, Collection $roles)` | 单条判定，与 `constrain()` 同一规则 |

- **模型 trait `HasDataScope`** 提供 `scopeVisibleTo()`（查询范围 `visibleTo($user)`）与 `isVisibleTo($user)`（单条判定），Policy 复用同一规则。两者的规则相同：
  1. 超管，或任一启用角色的范围是 `all` → 不限制；
  2. 否则各启用角色的范围做 OR 并集；
  3. 没有启用角色，或范围 key 未登记 → 默认拒绝（查询加 `1=0`，单条判定为否）。范围 key 未登记的角色不贡献任何范围，用户其他角色的范围照常生效。
- 停用的角色，其权限与数据范围都不生效。
- `DataScopeRegistry::isUnrestrictedFor($user)`：超管，或任一启用角色的范围是 `all` → 不受数据范围限制（`HasDataScope` 的第 1 条规则，二者共用）。
- 不属于某个用户、而是“关于”某个用户的记录（日志、会话、token），数据范围委托给关联的用户：`whereHas` 到 `User::visibleTo()`；不受限制的操作者另可看到没有关联用户的记录（§6.6）。
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
  - 非超管授予角色的权限码，必须是自己有效权限码的子集：提交的权限码中，不在操作者有效权限码里、且角色原本也没有的 → 422（`permissions`）；
  - 操作者管不到的授权保持不变：角色原本就有、但不在操作者有效权限码中的权限码（对非超管是自己没有的码，对超管是停用节点的授权——它们不出现在授权树中），无论是否提交都保留，操作者既不能加也不能删；
  - 只有超管能授予或收回 `super-admin` 角色（422，`roles`）。
  - **数据范围**：操作者“可授予的数据范围”（`DataScopeRegistry::grantableKeysFor()`，只算启用角色）——超管，或任一启用角色的范围是 `all` → 全部已登记的范围；否则为各启用角色选用的（已登记的）范围 key。角色管理中非超管提交的 `data_scope` 不在其中 → 422（`data_scope`）；修改时 `data_scope` 保持原值不拦，与权限码的保留规则同一思路。角色表单的选项（`/system/roles/data-scopes`）只列出可授予的范围。
  - **分配角色**：非超管只能分配或移除“管得到”的角色——角色的权限码全部在操作者的有效权限码之内（含停用节点授权的角色对非超管而言管不到），且角色的 `data_scope` 在操作者可授予的范围内。提交中新增的、管不到的角色 → 422（`roles`，列出角色标识）；用户原本就有、但操作者管不到的角色（含 `super-admin`），无论是否提交都保留不动，与上面权限码的保留规则同一思路。超管不受限。

### 8.6 业务保护规则

按 §6.1 的分工：只看“操作者 + 目标记录本身”就能判定的，由 Policy 返回 **403**；取决于提交内容或全局状态的，由 Action 返回 **422**，括号内是错误挂的字段（删除类只给 `message`）。

**用户**

- 不能删除或停用自己 —— 403（状态接口）；在修改接口中提交 `status: disabled` 停用自己 —— 422（`status`）。
- 不能在用户管理中重置自己的密码（应在个人设置中验证当前密码后修改）—— 403。
- 超管用户只有超管能编辑、重置密码、停用、删除 —— 403。
- 系统至少保留一个启用的超管（账号启用且拥有启用的 `super-admin` 角色）：最后一个启用的超管不能删除（`message`）、不能停用（`status`，修改接口与状态接口都拦）、不能去掉超管角色（`roles`）—— 422。
  - 并发：检查与写入在同一事务中，检查前先对 `super-admin` 角色那一行 `lockForUpdate()`，把所有“减少启用超管”的写操作（删除、停用、去掉超管角色）串行化，避免两个超管同时停用对方后一个不剩（`EnsureSuperAdminRemains`）。SQLite 不支持行锁，但同一时间只有一个写事务。
  - 通过接口时，非超管操作超管用户先被 403 拦住，超管操作者自己就是一个启用的超管，因此删除、停用的这条 422 实际只在并发时出现；去掉自己的超管角色在只剩自己时直接出现。
- 非超管授予或收回 `super-admin` 角色 —— 422（`roles`）。
- 停用走 `DisableUser`（吊销会话与 token，§7.3），启用走 `EnableUser`；管理员重置密码、删除用户后吊销对方的会话与 token（§7.4）。
- 分配角色的防提权见 §8.5（422，`roles`）。
- 用户名唯一，统一存为小写；重复时 422（`username`）；邮箱唯一，重复时 422（`email`）。表单校验之外，并发写入撞上唯一索引时 Action 同样返回 422。

**角色**

- `super-admin` 不能删除 —— 403；只有超管能修改、启停它 —— 403；不能停用 —— 422（`status`）；标识不能改 —— 422（`name`）。权限隐含全部，角色表单中不显示权限树；修改时提交 `permissions` —— 422（`permissions`）。
- 已分配给用户的角色不能删除 —— 422（`message`）。
- 授予的权限码超出自己的有效权限码 —— 422（`permissions`）；选用自己不可授予的数据范围 —— 422（`data_scope`，§8.5）。
- 停用角色的权限与数据范围都不生效。

**菜单节点**

- 内置节点不能删除 —— 403（超管也一样）。
- 内置节点的结构性字段 `name`、`type`、`path`、`component`、`meta.iframeSrc`、`meta.link` 不可修改：前端只读，提交的值与库中不同 —— 422（对应字段，如 `meta.link`）；它们只能由同步命令修改（§8.2）。
- `system.menu`、`system.menu.update` 两个节点及其祖先必须保持启用，防止把所有人锁在外面：停用它们或它们（修改后的）祖先 —— 422（`status`）；把它们或其祖先移到停用的节点下 —— 422（`parent_id`）。祖先按修改后的上级计算（`system.menu` 被移到别的目录下后，新目录不能停用，原来的 `system` 可以停用）；修改前就已失效的不拦，只拦本次修改造成的失效。
- 类型层级：`catalog` / `menu` / `embedded` / `link` 只能挂在根或 `catalog` 下，`button` 只能挂在 `menu` 下 —— 422（新增、移动上级时挂 `parent_id`；只改类型时挂 `type`）；修改类型后仍要能容纳现有的子节点 —— 422（`type`）。
- 上级不能是自己或自己的子孙（成环）—— 422（`parent_id`）。
- 有子节点的节点不能删除 —— 422（`message`）。删除节点时角色的授权随之删除（spatie 在删除前解除，外键 `cascadeOnDelete` 兜底）。
- 权限码全局唯一 —— 422（`name`）；非按钮节点的 `path` 唯一，且以 `/` 开头 —— 422（`path`）。并发写入撞上唯一索引时 Action 同样返回 422。
- 停用向子孙级联生效。

**字典**（§6.7）

- 内置的字典类型、字典项不能删除 —— 403（超管也一样）。
- 内置类型的 `code`、内置字典项的 `value` 不能修改 —— 422（`code` / `value`）；它们只能由同步命令维护。
- 类型的 `code` 唯一、同一类型内字典项的 `value` 唯一 —— 422（`code` / `value`）；并发写入撞上唯一索引时 Action 同样返回 422。
- 删除类型时连同其字典项一起删除（不做“有字典项不能删”，理由见 §6.7）。

**系统参数**（§6.7）

- 只能修改已声明的参数；未声明的键或分组 —— 404；分组批量修改中提交了未声明的参数名 —— 422（该键）。接口不能新增、删除参数（Policy 的 `create`、`delete` 一律拒绝）。
- 值不符合类型或声明的校验规则 —— 422（单个修改挂在 `value`，分组修改挂在参数名）。

**在线会话与 token**（§6.6）

- 强制下线自己当前的会话、吊销本次请求使用的 token —— 403（应使用退出登录）；自己的其他会话与 token 可以下线。
- 超管用户的会话与 token 只有超管能下线 —— 403。
- 所属用户超出数据范围 —— 403（列表中本来就看不到）。

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
| 日志 | 操作日志对密码、token 等字段脱敏（可配置，§6.6）；登录日志不记录密码；每个响应带 `X-Request-Id`，日志带同一请求 ID |

## 10. 编码规范

### 10.1 Laravel 写法

- 授权：控制器方法上用 `#[Authorize]`；路由文件只描述结构（分组导入、`apiResources`）。
- 模型：`#[Fillable]`、`#[Hidden]`、`#[ObservedBy]` 等 Attribute（工厂、Policy 按默认规则自动推断，不需要时不写 `#[UseFactory]`、`#[UsePolicy]`）；`casts()` 方法；禁止 `$fillable` 等属性写法。
- 命令：`#[Signature]`、`#[Description]`。队列任务：`#[Tries]`、`#[Backoff]` 等，**队列名不写在任务上**，统一用 `Queue::route()` 集中登记。
- 统计类数据用 `Cache::flexible()`；请求内缓存用 `Cache::memo()` 或 `#[Scoped]`。
- 时间：写库前统一转换为应用时区；输出带时区偏移的 ISO 8601。
- 枚举：实现统一的 `HasLabel`（多语言，必须，`tests/Arch` 强制）/ `HasColor`（语义色，可选）接口，由后端统一下发选项（在 `EnumRegistry` 中登记后由 `/options/enums` 下发，§6.7）。
  - `App\Core\System\Contracts\HasColor::color(): string`：取值规则与字典项的 `color` 相同——antd Tag 的预设色名（`success`、`processing`、`error`、`warning`、`default`、`blue` 等）或 `#RGB` / `#RRGGBB`；登记时校验。表示“状态”的枚举（启用 / 停用、成功 / 失败）实现它，前端的 Tag 直接使用；纯分类的枚举不实现。`core:make-enum` 的模板不带 `color()`，需要时手工加上 `HasColor`。
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
- 完整增删改查还会生成：权限节点（写入模块的 `resources/menus.php`，§8.2）、路由、**授权测试**、模块 README（操作日志的文案由前端按路由名翻译，§6.6）。
- **新建文件必须使用生成器**，写入 `CLAUDE.md`；放错位置由架构测试拦截。

### 10.5 测试

- Pest；`tests/` 目录与 `app/` 对应。
- `tests/Pest.php` 提供统一辅助：同步内置节点（`syncBuiltinPermissions()`）、拥有某些权限码的用户（`userWithPermissions()`，角色范围为 `self`）、指定数据范围的用户（`userWithDataScope()`）、超管（`superAdminActor()`）；403、数据权限过滤用框架自带的断言（`assertForbidden()`、`Gate::forUser()->inspect()` 等）。
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
| 队列 | `queue:work` 或 Horizon（Redis + Linux），由 Supervisor / systemd 管理；须处理默认队列（健康检查的队列心跳、定时任务的“立即执行”，§6.6） |
| 调度 | cron 每分钟调用 `schedule:run`，或 `schedule:work`；健康检查的调度心跳依赖它（§6.6） |
| Reverb | 启用实时推送时 |

### 11.4 发布流程

1. 数据库迁移（迁移必须向下兼容）；
2. 切换新代码或新镜像；
3. `php artisan optimize`，然后 `php artisan core:sync` 依次同步模块声明的菜单与权限节点（§8.2）、字典与系统参数（§6.7），任何一个失败即以非 0 退出、中止发布；再 `php artisan schedule-monitor:sync` 把调度定义同步到定时任务监控（§6.6；会删除调度中已不存在的任务）；
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
| laravel/pulse 1.11 | 生产性能监控（接入方式待定，暂不安装，§14） |
| laravel/octane 2.25 | 可选：常驻内存运行（FrankenPHP） |
| laravel/horizon 5.52 | 可选：Redis 队列管理（需 pcntl，Linux） |
| laravel/reverb 1.14 | 可选：实时推送 |
| laravel/pennant 1.28 | 可选：功能开关 |

**spatie**

| 包 | 用途 |
|---|---|
| laravel-permission 8.5 | 角色与权限存储 |
| laravel-query-builder 7.3 | 列表筛选、排序、关联加载 |
| laravel-activitylog 5.1 | 数据变更审计、操作日志、登录日志（统一存储，按日志名区分，§6.6）。已核实 5.1.5：有状态的服务（`CauserResolver`、`ActivityLogStatus`、`ActivityBuffer`）都以 `scoped` 注册，Octane 下无需登记 flush；不使用其静态的 `beforeLogging()` 回调 |
| laravel-backup 10.4 | 数据库与文件备份 |
| laravel-health 1.42 | 健康检查（§6.6）。已核实 1.42.0：`illuminate/*` ^13.0；结果存缓存（`CacheHealthResultStore`）；`Health` 单例只在启动时登记检查，不保存请求数据；不注册其 HTTP 端点 |
| laravel-schedule-monitor 4.3 | 定时任务执行监控（§6.6）。已核实 4.3.3：`illuminate/bus` ^13.0；单例 `MonitoredScheduledTasks` 只在定义调度时按事件对象记录 `monitorName` 等设置，不保存请求数据；不使用 Oh Dear（未配置 `monitor_id` 时同步只写本地表） |
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
| 菜单权限 | 建在 `permissions` 表上的菜单树（Vben 的 5 种节点类型，按类型的规则在 `PermissionType`）、扩展点 `MenuRegistry`（`resources/menus.php` 声明，注册时校验）与同步命令 `core:sync-permissions`、`Gate::before` 与 `#[Scoped]` 的 `PermissionRegistry`（有效权限码、停用级联）、按 Vben 路由结构输出菜单（`/menu/all`）、权限码接口（`/auth/codes`）、菜单管理（类型校验、层级、成环、内置节点与生命线保护） | spatie/laravel-permission、Gate |
| 角色与用户的防提权 | 授予权限码、数据范围、分配角色时的“只能给自己有的”、保留操作者管不到的授权、最后一个超管（加锁） | spatie/laravel-permission、数据库行锁 |
| 数据权限 | `DataScopeProvider` 接口与 `DataScopeRegistry`（含可授予范围的防提权）、默认“全部 / 仅本人”、模型 trait `HasDataScope`（`visibleTo()` / `isVisibleTo()`，归属列可声明） | Eloquent 查询范围、Policy |
| 列表查询 | 时间区间筛选 `DateTimeRangeFilter`（ISO 8601 带时区）；query-builder 的未知筛选 / 排序异常（`InvalidQuery`，默认 400）统一映射为 422（`VbenResponse`） | spatie/laravel-query-builder |
| 认证补充 | 登录日志（认证事件的监听、限流时派发 `Lockout`）、在线会话与强制下线（会话表的只读模型、token 列表、当前会话与超管保护）、禁用账号立即生效、设备 token 签发、指定角色强制两步验证 | Fortify、Sanctum、认证事件、数据库会话 |
| 操作日志 | 记录写请求的中间件（排除认证请求、脱敏、耗时、请求 ID，响应后写入且失败不影响响应）、日志的只读查询接口（数据范围委托给操作者）、按日志名区分的模型 | spatie/laravel-activitylog（含 `activitylog:clean`）、`Context`、`defer()` |
| 字典 | 字典类型 / 字典项与管理接口（内置项的保护）、扩展点 `DictRegistry`（`resources/dicts.php` 声明）与同步命令 `core:sync-dicts`、整体缓存的读取服务 `Dictionary`、`InDict` 校验规则、选项下发接口（`/options/dicts`） | Cache（`Cache::memo()`）、Validation、模型事件（提交后清除缓存） |
| 系统参数 | 后台可修改值的键值参数（只能改已声明的，`null` 表示使用默认值）、扩展点 `SettingRegistry`（`resources/settings.php` 声明分组、类型、默认值或 config 键、是否公开、校验规则、引用的字典）与同步命令 `core:sync-settings`（三个同步由 `core:sync` 依次执行）、按类型校验与转换（`SettingType`）、读取服务 `Settings`（缓存、默认值）、按分组的管理接口、公开参数接口（`/settings/public`，只输出声明为公开的） | Cache（`Cache::memo()`）、Validation、`json` cast |
| 附件 | 上传记录、归属校验、删除时清理文件 | Storage |
| 通知中心 | 我的通知、已读、全部已读接口 | Laravel 数据库通知 |
| 公告 | 公告管理、发布后分批群发 | Notifications、`Bus::batch` |
| 定时任务 / 失败任务 | 定时任务与执行记录的只读接口（读 schedule-monitor 的表）；立即执行（按任务名在调度事件中查找、入队，与 `schedule:run` 相同地派发事件）；失败任务的只读模型与列表、详情；payload 与异常的脱敏、截断（与操作日志共用 `DataMasker`） | spatie/laravel-schedule-monitor、`queue:retry`、`FailedJobProviderInterface` |
| 系统监控 | 健康检查的选择与阈值、最新结果与立即检查接口、系统信息接口 | spatie/laravel-health（`health:check`）；Pulse 待定（§14） |
| 枚举选项 | `HasLabel` / `HasColor` 接口、显式白名单 `EnumRegistry`（模块在 Provider 中登记对外名称，登记时校验颜色）、选项下发接口（`/options/enums`，按 `Accept-Language` 翻译，有颜色时输出 `color`） | PHP backed enum、翻译 |
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
| 跨数据库实测 | 操作日志、登录日志的 JSON 路径筛选（`properties->…`，含 `status` 的整数比较）与在线会话的前缀绑定（`LIKE '<前16位>%'` 后按大小写精确比较）目前只在 SQLite 上测试，待在 MySQL / PostgreSQL 上实测（CI 加入数据库矩阵时，§6.6） |
| Pulse | 设计原定“系统监控依托 Pulse”，但 Pulse 的仪表盘是 Livewire 页面，与“不渲染页面”（§1）冲突。暂不安装；接入时只用它的记录器写入 `pulse_*` 表，由后台接口读取聚合数据给前端图表，`/pulse` 页面最多作为只对超管开放的运维入口 |
| `core:make-module --crud` | 未完成：§10.4 的完整增删改查生成（含写入模块的 `resources/menus.php`、路由、授权测试）尚未提供；目前用 `core:make-model`、`core:make-controller` 等逐个生成，菜单节点手工写入 `menus.php` |
