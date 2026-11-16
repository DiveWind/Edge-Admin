# Edge Admin · Web

Edge Admin 的前端，基于 Ant Design Pro v6（Umi Max 4 + antd 6 + ProComponents 3）。接口契约见 [`../docs/architecture.md`](../docs/architecture.md)。

## 启动

```bash
# 1. 后端（另一个终端）
cd ../server && php artisan serve --port=8000
# 发布公告通知、立即执行定时任务需要队列
cd ../server && php artisan queue:work

# 2. 前端
npm install
npm run dev        # http://localhost:8001 ，/api 与 /storage 代理到 127.0.0.1:8000
```

默认账号 `admin` / `password`。

## 常用命令

| 命令                        | 说明                                                                          |
| --------------------------- | ----------------------------------------------------------------------------- |
| `npm run dev` / `npm start` | 开发服务器（端口见 `.env`）                                                   |
| `npm run build`             | 生产构建，产物在 `dist/`；部署时由 Web 服务器把 `/api`、`/storage` 转发到后端 |
| `npm run preview`           | 本地预览构建产物                                                              |
| `npm run lint`              | Biome lint + TypeScript 类型检查                                              |
| `npm run biome`             | Biome 自动修复与格式化                                                        |
| `npm test`                  | 单元测试（Vitest）                                                            |
| `npx antd lint ./src`       | antd 用法检查                                                                 |

开发规范与目录约定见 [CLAUDE.md](./CLAUDE.md)。
