# 快速开始

## 1. 环境要求

| 项 | 版本 |
|---|---|
| PHP | ≥ 8.3（扩展：pdo_sqlite / pdo_mysql / pdo_pgsql 之一、mbstring、fileinfo、gd 可选） |
| Composer | 2.x |
| Node.js | ≥ 22（前端与 `composer dev` 的多进程启动需要） |
| 数据库 | 默认 SQLite，可切换 MySQL 8+ / PostgreSQL 14+（代码不含方言 SQL） |

## 2. 后端

```bash
cd server
composer setup   # 安装依赖、生成 .env 与 APP_KEY、创建 SQLite 库、migrate --seed、storage:link
composer dev     # 同时启动 serve(8000) + queue:listen + schedule:work + pail 日志
```

也可以分别启动：

```bash
php artisan serve          # http://127.0.0.1:8000
php artisan queue:listen   # 公告通知、"立即执行"定时任务走队列
php artisan schedule:work  # 本地调度器
```

- 健康检查 `GET /up`；`GET /` 返回 `{name, version}`；
- OpenAPI 文档 <http://127.0.0.1:8000/docs/api>（仅 `APP_ENV=local`，由 Scramble 生成）；
- 上传文件存放在 `public` 磁盘，URL 前缀取自 `APP_URL`。

### 切换 MySQL / PostgreSQL

修改 `server/.env` 中的 `DB_*`（`.env.example` 内有示例），然后：

```bash
php artisan migrate:fresh --seed
```

### 可选：Laravel Sail（Docker）

仓库已包含官方 `laravel/sail`（dev 依赖）与 `server/compose.yaml`（PHP 8.3 + MySQL 8.4 + Redis）。只想用 Docker 时：

```bash
cd server
# 按 .env.example 末尾的 "Laravel Sail" 注释修改 .env（DB_HOST=mysql、REDIS_HOST=redis 等）
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
```

Sail 只是可选的本地环境，测试（`composer test`）始终使用 SQLite 内存库。

## 3. 前端

```bash
cd web
npm ci
npm run dev      # http://localhost:8001，/api 代理到 127.0.0.1:8000
```

前端说明见 [`web/CLAUDE.md`](../web/CLAUDE.md) 与 [frontend-guide.md](frontend-guide.md)。

## 4. 默认账号

密码均为 `password`：`admin`（超级管理员）、`editor`（内容编辑）、`viewer`（只读访客）、`marketing`（市场主管，自定义数据权限）、
`disabled`（已禁用）。详见 [architecture.md](architecture.md) 第 6 节。

## 5. 常用命令（`server/`）

| 命令 | 说明 |
|---|---|
| `composer lint` / `composer format` | Pint 检查 / 自动格式化 |
| `composer analyse` | Larastan（level 8） |
| `composer test` | Pest（架构测试 + Feature + Unit） |
| `php artisan migrate:fresh --seed` | 重建数据库并填充演示数据 |
| `php artisan permission:sync` | 同步 `config/permissions.php` 中的权限点 |
| `php artisan edge:make-module` | 代码生成器，见 [generator.md](generator.md) |
| `php artisan schedule:list` | 查看定时任务 |
| `php artisan queue:retry all` | 重试全部失败任务 |

## 6. 下一步

- 接口契约：[architecture.md](architecture.md)
- 后端分层规范：[backend-guide.md](backend-guide.md)
- 权限：[permissions.md](permissions.md)
- 部署：[deployment.md](deployment.md)
