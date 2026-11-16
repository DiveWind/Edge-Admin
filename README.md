# Edge Admin

[![CI](https://github.com/edge-admin/edge-admin/actions/workflows/ci.yml/badge.svg)](.github/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)
![Ant Design 6](https://img.shields.io/badge/Ant%20Design-6-1677FF)

Edge Admin 是一个前后端分离的 **RBAC 中后台框架**：后端是纯 API 的 Laravel 应用（`server/`），前端是 Ant Design Pro（`web/`），
两端只通过 [`docs/architecture.md`](docs/architecture.md) 中的 REST 契约通信，可独立开发、独立部署。

它的目标是"**可以直接当作新项目起点的规范框架**"：只用 Laravel 官方能力（Sanctum、Gate / Policy、Form Request、API Resource、
队列、调度、通知），分层约定由架构测试强制执行，并附带一个能生成同等规范代码的模块生成器。

> 下文中的仓库地址、徽章链接为占位，fork 后请替换为你自己的仓库。

## 特性

- **认证**：Sanctum API Token，登录失败限流（只计失败、成功清零，次数可在线调整）、登录日志、修改密码时其他设备下线
- **权限**：功能权限在代码中声明（`config/permissions.php` → `permission:sync` → 路由 `can:`），无第三方权限包；
  记录级规则写在 Policy；**五种数据权限**（全部 / 自定义部门 / 本部门及下级 / 本部门 / 本人），多角色取并集
- **系统管理**：用户、角色、权限、部门（树）、岗位、**数据字典**（带缓存 + `InDict` 校验）、**系统参数**（类型化、内置参数保护、
  免登录的公开参数）、**附件管理**
- **系统监控**：登录日志、操作日志（自动记录、脱敏、双语摘要）、**在线会话**（强制下线）、**系统信息**、定时任务（查看 / 立即执行 / 执行日志）、失败队列
- **消息**：通知公告（发布后队列推送站内信）、我的通知
- **多语言**：按 `Accept-Language` 返回中文 / 英文；401 / 403 / 404 / 405 / 422 / 429 统一 JSON 错误
- **时区**：输入的日期时间按 ISO 8601 偏移换算到 `APP_TIMEZONE`（不带偏移按应用时区解释），输出一律带偏移
- **代码生成器**：`php artisan edge:make-module Tag --module=content --fields="name:string:required|max:50,status:status"`
  一次生成迁移、模型、工厂、请求、资源、Policy、控制器、测试与前端页面（列表 + 独立表单组件，带与后端一致的必填 / 长度校验、
  外键下拉选项、可选的 `--with-options` 选项接口），并幂等登记权限点 / 路由 / 菜单 / 文案；生成后 `permission:sync` + `migrate` 即可通过两端全部检查
- **工程化**：Pest（含架构测试）· Larastan level 8 · Pint · GitHub Actions · 数据库无关（SQLite / MySQL / PostgreSQL）· 可选 Laravel Sail
- **业务示例**：接入数据权限与数据字典的"文章"模块，作为业务开发范本

## 技术栈

| | 后端 `server/` | 前端 `web/` |
|---|---|---|
| 语言 / 框架 | PHP 8.3 · Laravel 13 | TypeScript · React 19 · Umi Max · Ant Design Pro |
| 认证 | Laravel Sanctum（API Token） | Bearer Token |
| 权限 | 原生 Gate / Policy / `can:` 中间件 | `access.ts` |
| 队列 / 调度 / 通知 | database 队列（可换 Redis）· Laravel Schedule · database notifications | — |
| API 文档 | dedoc/scramble（`/docs/api`，仅 local） | — |
| 质量 | Pest · Pint · Larastan · Laravel Boost | Biome · tsc · `npx antd lint` |

## 截图

> 截图待补充：将图片放在 `docs/images/` 并在此引用，例如 `![工作台](docs/images/dashboard.png)`。
> 建议包含：登录页、工作台、用户管理、角色授权、数据字典、在线会话、系统信息。

## 快速开始

环境要求：PHP ≥ 8.3（pdo_sqlite、mbstring、fileinfo）、Composer 2、Node ≥ 22。

```bash
# 后端：安装依赖、生成 .env 与 APP_KEY、创建 SQLite 库、迁移并填充演示数据
cd server
composer setup
composer dev            # serve(8000) + queue + schedule + 日志

# 前端
cd ../web
npm ci
npm run dev             # http://localhost:8001，/api 代理到 8000
```

更多方式（MySQL / PostgreSQL、Laravel Sail）见 [快速开始](docs/getting-started.md)。

### 默认账号

密码均为 `password`。

| 账号 | 角色 | 数据权限 |
|---|---|---|
| `admin` | 超级管理员（id=1，受保护） | 全部 |
| `editor` | 内容编辑（研发中心） | 本部门及下级部门 |
| `viewer` | 只读访客（前端组） | 仅本人 |
| `marketing` | 市场主管（市场部） | 自定义部门（市场部及华东、华南区） |
| `disabled` | — | 已禁用，无法登录 |

## 文档

| 文档 | 内容 |
|---|---|
| [快速开始](docs/getting-started.md) | 环境、本地运行、常用命令 |
| [架构与接口契约](docs/architecture.md) | 前后端唯一依据：接口、字段、权限码、错误格式、初始数据 |
| [后端开发规范](docs/backend-guide.md) | 分层职责、何时用 Action、列表 / 错误 / 多语言 / 测试规范 |
| [权限](docs/permissions.md) | 功能权限、记录级规则、数据权限的使用与扩展 |
| [代码生成器](docs/generator.md) | 字段 DSL、模板与占位符、锚点约定 |
| [部署](docs/deployment.md) | Nginx、队列、调度、环境变量 |

## 目录结构

```
.
├── server/                        # Laravel 应用（纯 API）
│   ├── app/
│   │   ├── Actions/               # 写操作用例（多步写入 / 带副作用），单一 handle()
│   │   ├── Casts/ Collections/    # SettingValue、TreeCollection
│   │   ├── Console/               # permission:sync、edge:make-module 及其生成引擎
│   │   ├── Enums/                 # Status、DataScope、SettingType…
│   │   ├── Http/
│   │   │   ├── Controllers/Api/{Auth,System,Monitor,Message,Content,Options}/
│   │   │   ├── Middleware/        # SetLocale、EnsureUserIsActive、RecordOperationLog
│   │   │   ├── Requests/          # Form Request（ListRequest 为分页列表基类）
│   │   │   └── Resources/         # API Resource
│   │   ├── Jobs/ Listeners/ Notifications/
│   │   ├── Models/                # Eloquent 模型；Concerns/HasDataScope
│   │   ├── Policies/              # 记录级授权
│   │   ├── Rules/                 # InDict
│   │   └── Services/              # Dictionary、Settings、SystemInfo、DataScopeResolver…
│   ├── config/edge.php            # 项目配置
│   ├── config/permissions.php     # 权限点声明（树）
│   ├── database/                  # 迁移、Factory、Seeder
│   ├── lang/{zh_CN,en}/           # 双语文案、操作日志摘要
│   ├── routes/api.php             # 全部接口（含 can: 权限声明）
│   ├── stubs/edge/                # 代码生成器模板（可直接修改定制）
│   └── tests/{Arch,Feature,Unit}/ # Pest
├── web/                           # Ant Design Pro 前端
├── docs/                          # 项目文档
└── .github/                       # CI、Issue / PR 模板
```

## 参与贡献

欢迎 Issue 与 PR！请先阅读 [CONTRIBUTING.md](CONTRIBUTING.md)；安全问题请按 [SECURITY.md](SECURITY.md) 私密报告。变更记录见 [CHANGELOG.md](CHANGELOG.md)。

## License

[MIT](LICENSE) © 2026 Edge Admin contributors
