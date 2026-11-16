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
| `npm run e2e`               | Playwright 端到端测试（见下）                                                 |

## 端到端测试

`npm run e2e` 运行 `e2e/*.spec.ts`（Playwright，真实后端，不在 `npm test` 与 CI 必过项中）。运行前：

```bash
cd ../server && php artisan migrate:fresh --seed && php artisan serve --port=8000
cd ../server && php artisan queue:work      # 公告通知用例需要
```

前端开发服务器未运行时会自动启动（`E2E_BASE_URL` 可指向其他地址）。浏览器使用预装 Chromium，`@playwright/test` 版本固定，不要执行 `playwright install`。

## 文档

开发规范见 [../docs/frontend-guide.md](../docs/frontend-guide.md)，精简约定见 [CLAUDE.md](./CLAUDE.md)，
代码生成器（前后端一次生成单表 CRUD）见 [../docs/generator.md](../docs/generator.md)。
