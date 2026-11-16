# 部署

推荐形态：**同域部署**——Nginx 托管前端构建产物 `web/dist`（SPA），`/api`、`/storage`、`/up` 转发给 PHP-FPM 运行的 Laravel。
这样无需 CORS，前端请求直接用相对路径 `/api`。

## 1. 后端

```bash
cd /srv/edge-admin/server
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate   # 首次
# 编辑 .env（见第 5 节）
php artisan migrate --force
php artisan db:seed --force                         # 仅首次：内置字典、系统参数、演示数据（生产可按需删减 Seeder）
php artisan permission:sync                         # 每次部署
php artisan storage:link                            # 首次
php artisan optimize                                # 缓存 config / routes / events / views
php artisan queue:restart                           # 每次部署后让常驻 worker 重新加载代码
```

- 生产环境（`APP_ENV=production`）下 `migrate:fresh`、`db:wipe` 等破坏性命令被禁止（`DB::prohibitDestructiveCommands`）；
- `php artisan optimize` 之后修改 `.env` 需重新执行 `php artisan optimize`（或 `optimize:clear`）；
- `storage/` 与 `bootstrap/cache/` 需对 PHP-FPM 用户可写。

## 2. 前端

```bash
cd /srv/edge-admin/web
npm ci && npm run build      # 产物在 web/dist
```

## 3. Nginx

```nginx
server {
    listen 80;
    server_name admin.example.com;
    # 生产请配置 443 + TLS，并设置 EDGE_FORCE_HTTPS=true（TLS 在负载均衡终止时）

    root /srv/edge-admin/web/dist;
    index index.html;
    client_max_body_size 20m;   # 不小于 EDGE_UPLOAD_MAX_SIZE 与 PHP 的 upload_max_filesize / post_max_size

    # Laravel：/api、健康检查，统一交给 server/public/index.php
    location ~ ^/(api|up)(/|$) {
        root /srv/edge-admin/server/public;
        try_files $uri /index.php?$query_string;
    }

    # 上传文件（php artisan storage:link 生成 server/public/storage）
    location /storage/ {
        root /srv/edge-admin/server/public;
        try_files $uri =404;
        add_header X-Content-Type-Options nosniff;
        expires 30d;
    }

    location = /index.php {
        root /srv/edge-admin/server/public;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # 前端静态资源
    location ~* \.(?:js|css|png|jpg|jpeg|gif|svg|ico|woff2?)$ {
        try_files $uri =404;
        expires 7d;
    }

    # SPA fallback
    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

前后端分域部署时：前端 `/api` 指向后端域名，后端 `.env` 设置 `CORS_ALLOWED_ORIGINS=https://admin.example.com`。

> 部署在负载均衡 / 反向代理之后时，若需要获取真实客户端 IP（登录日志、在线会话、限流），请在 `bootstrap/app.php` 中配置
> `$middleware->trustProxies(at: [...代理 IP...])`，不要对公网直连的实例信任任意代理头。

## 4. 队列与调度

公告通知、"立即执行"定时任务依赖队列；日志清理、过期 token 清理依赖调度。

### Supervisor

```ini
; /etc/supervisor/conf.d/edge-admin-worker.conf
[program:edge-admin-worker]
command=php /srv/edge-admin/server/artisan queue:work --sleep=3 --tries=3 --max-time=3600
process_name=%(program_name)s_%(process_num)02d
numprocs=2
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/srv/edge-admin/server/storage/logs/worker.log
```

### 或 systemd

```ini
# /etc/systemd/system/edge-admin-worker.service
[Unit]
Description=Edge Admin queue worker
After=network.target

[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php /srv/edge-admin/server/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

### 调度（cron）

```cron
* * * * * cd /srv/edge-admin/server && php artisan schedule:run >> /dev/null 2>&1
```

内置定时任务：`model:prune`（每日，清理超过系统参数 `log.retention_days` 天的登录 / 操作 / 任务日志）、
`sanctum:prune-expired --hours=24`、`queue:prune-failed --hours=168`。可在"系统监控 / 定时任务"中查看与立即执行。

## 5. 环境变量

Laravel 标准变量（`APP_*`、`DB_*`、`CACHE_STORE`、`QUEUE_CONNECTION`、`REDIS_*`、`MAIL_*`、`FILESYSTEM_DISK`、`AWS_*`）见 `.env.example`。生产至少设置：

| 变量 | 生产建议 |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | 对外访问地址（上传文件 URL 依赖它） |
| `APP_KEY` | `php artisan key:generate` |
| `APP_LOCALE` / `APP_TIMEZONE` | 接口默认语言（`zh_CN` / `en`）/ 时区 |
| `DB_*` | MySQL / PostgreSQL 连接 |
| `CACHE_STORE` / `QUEUE_CONNECTION` | 建议 `redis`（字典与系统参数缓存、队列） |
| `CORS_ALLOWED_ORIGINS` | 分域部署时前端域名，逗号分隔 |

Edge Admin 自定义变量（`config/edge.php`）：

| 变量 | 默认 | 说明 |
|---|---|---|
| `APP_VERSION` | `1.0.0` | 版本号，`GET /` 与系统信息返回 |
| `EDGE_PER_PAGE` / `EDGE_MAX_PER_PAGE` | 15 / 100 | 分页默认值 / 上限 |
| `EDGE_API_RATE_LIMIT` | 120 | `/api/*` 每分钟请求上限 |
| `EDGE_FORCE_HTTPS` | false | 强制生成 https 链接 |
| `EDGE_TOKEN_TTL` | 10080 | 登录 token 有效期（分钟） |
| `EDGE_LOGIN_MAX_ATTEMPTS` | 5 | 每分钟每个"用户名 + IP"允许的登录失败次数默认值（成功登录不计数并清零；系统参数 `security.login_max_attempts` 优先） |
| `EDGE_LOG_RETENTION_DAYS` | 90 | 日志保留天数默认值（系统参数 `log.retention_days` 优先） |
| `EDGE_UPLOAD_DISK` | public | 附件磁盘（可换 s3 等） |
| `EDGE_UPLOAD_MAX_SIZE` | 2048 | 单文件上限（KB） |
| `EDGE_UPLOAD_EXTENSIONS` | 图片 + 常见文档 | 允许的扩展名，逗号分隔（按真实 MIME 校验，不建议加入 svg / html） |
| `EDGE_GENERATOR_WEB_PATH` | `../web` | 代码生成器的前端目录（仅开发环境使用） |

## 6. 上线检查

- [ ] `APP_DEBUG=false`，`/docs/api` 不可访问（Scramble 默认仅 local）
- [ ] `php artisan permission:sync` 已执行，超级管理员可登录
- [ ] 队列 worker 与 cron 已运行（"系统监控 / 定时任务"能看到执行日志）
- [ ] 上传后 `/storage/...` 可访问；系统信息中的磁盘 / 数据库指标正常
- [ ] 修改默认账号密码，或删除演示账号
