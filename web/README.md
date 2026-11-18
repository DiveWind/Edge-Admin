# web

<!-- cspell:ignore commitlintrc -->

本脚手架的后台前端：基于 [Vben Admin 5](https://github.com/vbenjs/vue-vben-admin)（antdv-next 版）裁剪而来的 pnpm + Turborepo monorepo。

- 只保留一个应用 `apps/web`（由上游 `apps/web-antdv-next` 改名而来），以及开发期参考用的 `playground` 与它依赖的 `apps/backend-mock`。
- 与后端（`../server/`）只通过接口契约通信，契约见 [`../docs/backend-design.md`](../docs/backend-design.md) §6。
- 开发约定见 [`CLAUDE.md`](CLAUDE.md)。

## 上游版本

| 项 | 值 |
| --- | --- |
| 上游仓库 | <https://github.com/vbenjs/vue-vben-admin> |
| 所用版本 | `v5.7.0`（最新正式发布 tag，未使用 `main` 上未发布的提交） |
| 对应 commit | `63a38dce49ba109f61607994e21ba921d8e970e9`（2026-05-21） |
| 获取方式 | `git clone --depth 1 --branch v5.7.0`，删除 `.git` 后放入本目录 |
| 许可证 | MIT，上游版权声明保留在 [`LICENSE`](LICENSE) |

升级上游时更新本表。

## 环境

- Node.js `^22.18.0 || ^24`（`.node-version` 为 22.22.0）
- pnpm：版本由 `package.json` 的 `packageManager` 固定（当前 `pnpm@10.33.4`），本机装有 pnpm 10+ 即可自动切换；只能用 pnpm（`preinstall` 会拦截 npm / yarn）
- npm 源：`.npmrc` 使用官方源 `https://registry.npmjs.org`

## 常用命令

在本目录（`web/`）下执行：

| 命令 | 说明 |
| --- | --- |
| `pnpm install` | 安装依赖（`postinstall` 会构建内部包的 stub） |
| `pnpm dev` | 启动 `apps/web` 开发服务器（端口 5999，`/api` 由 Vite 代理到 `http://localhost:8000`，路径不改写） |
| `pnpm build` | 构建 `apps/web`，产物在 `apps/web/dist`（同时生成 `dist.zip`） |
| `pnpm build:analyze` | 构建 `apps/web` 并生成体积分析报告 |
| `pnpm preview` | 预览 `apps/web` 的构建产物（需先 `pnpm build`） |
| `pnpm dev:play` / `pnpm build:play` | 启动 / 构建 `playground`（仅作参考） |
| `pnpm check:type` | 类型检查（`vue-tsc`，覆盖 `apps/web` 与 `playground`） |
| `pnpm lint` | oxfmt（格式检查）+ oxlint + ESLint + Stylelint |
| `pnpm format` | 自动修复格式与可修复的 lint 问题 |
| `pnpm test:unit` | Vitest 单元测试（共享包内的 `__tests__`） |
| `pnpm test:e2e` | Playwright 端到端测试（目前只有 `playground` 有用例） |
| `pnpm check` | 循环依赖 + 未使用依赖 + 类型 + 拼写检查 |
| `pnpm clean` / `pnpm reinstall` | 清理 `node_modules`、`dist` 等 / 清理后重新安装 |

> 上游的 `date.test.ts` 断言系统时区是 `Area/City` 形式，在时区为 `UTC` 的机器上会失败。CI 与上游一致，设置 `TZ=Asia/Shanghai`；本地如遇到可用 `TZ=Asia/Shanghai pnpm test:unit`。

## 目录结构

```
web/
├── apps/
│   ├── web/            本项目的后台应用（包名 web）
│   └── backend-mock/   上游的 Nitro mock 服务（开发期参考，发布前删除）
├── playground/         上游的功能演示应用（开发期参考，发布前删除）
├── packages/           Vben 共享包（@vben/*、@vben-core/*），不改名、尽量不改
├── internal/           构建与规范配置（vite / tsconfig / tailwind / lint）
├── scripts/            vsh 命令行工具与 clean 脚本
├── package.json        根脚本（包名 web-workspace）
├── pnpm-workspace.yaml 工作区与依赖目录（catalog）
└── turbo.json
```

## playground 与 backend-mock

`playground` 是上游的完整功能演示（系统管理 CRUD、表单、表格、弹窗、权限等示例），`apps/backend-mock` 是它使用的 Nitro mock 服务。二者**只在开发期作为迁移页面的参考**，不是交付物：

- 写新页面时可对照 `playground/src/views/**` 的用法；
- 发布前删除 `playground/` 与 `apps/backend-mock/`（并移除 `pnpm-workspace.yaml` 中的 `playground`、根脚本 `dev:play` / `build:play`、`internal/lint-configs` 里对 `apps/backend-mock` 的规则覆盖）；
- `apps/web` 已对接 Laravel 后端，不再使用 Nitro mock（`.env.development` 中 `VITE_NITRO_MOCK=false`）。

## 相对上游的裁剪与改动

**删除**

- 应用：`apps/web-antd`、`apps/web-ele`、`apps/web-naive`、`apps/web-tdesign`；文档站 `docs/`。
- 仓库级文件：`.github/`、`.changeset/`、`scripts/deploy/`（Dockerfile / nginx）、`.vscode/`、`vben-admin.code-workspace`、`.gitpod.yml`、`tea.yaml`、`.gitconfig`、`README.zh-CN.md`、`README.ja-JP.md`。
- 提交工具与 git 钩子（本项目不使用 git 钩子）：`lefthook.yml`、`.commitlintrc.js`、`internal/lint-configs/commitlint-config`（`@vben/commitlint-config`）及根脚本 `prepare`、`commit`。
- 发版工具：changesets（根脚本 `changeset`、`version`）。
- `scripts/turbo-run`（`@vben/turbo-run`，多应用时交互选择要启动的应用，只剩一个应用后不再需要）。
- 根脚本：`dev:antd`、`dev:ele`、`dev:naive`、`dev:tdesign`、`dev:antdv-next`、`dev:docs`、`build:antd`、`build:ele`、`build:naive`、`build:tdesign`、`build:docs`、`build:docker`、`bootstrap`、`catalog`。
- `pnpm-workspace.yaml`：工作区去掉 `docs`；catalog 去掉 `ant-design-vue`、`element-plus`、`naive-ui`、`tdesign-vue-next`、`unplugin-element-plus`，以及只被上述删除项使用的 changesets、commitlint、czg / cz-git、lefthook、is-ci、vitepress 相关、`medium-zoom`、`@clack/prompts`；`publicHoistPattern` / `allowBuilds` 中去掉 lefthook、commitlint、czg。

**修改**

- `apps/web-antdv-next` → `apps/web`，包名 `@vben/web-antdv-next` → `web`，版本 `0.0.0`。
- `apps/web/.env`：标题 `Admin`、命名空间 `admin-web`（不含 UI 库名）。
- `apps/web/.env*`：接口地址由上游公开演示服务 `https://mock-napi.vben.pro/api` 改为同源 `/api/admin`（后端后台入口前缀）；路由统一为 hash 模式（找回密码邮件链接为 `/#/auth/reset-password`）。
- `apps/web` 认证改为 Sanctum 会话 Cookie + Fortify：请求层、登录 / 两步验证 / 找回与重置密码页、个人中心（资料、密码、两步验证）；删除上游演示路由与页面（`vben`、`demos`、分析页与工作台示例、验证码 / 扫码登录、注册、关于）、布局中的文档 / GitHub / 问题帮助菜单与 mock 通知；logo、默认头像改为本地文件（`public/`），版权方为应用标题。
- `apps/web/package.json`：开发依赖 `axios-mock-adapter`（请求层单元测试）。
- `apps/web/index.html`：去掉上游注入的百度统计脚本。
- 根 `package.json`：包名 `web-workspace`，脚本见上表；`.npmrc` 改为官方源。

**保留（与 UI 库名相关但属于共享包，未改）**

- `packages/styles` 中 `antd` / `ele` / `naive` 的样式重置 CSS 与导出（纯 CSS，不依赖对应 UI 库）；
- `packages/icons` 的 TDesign 图标、`packages/@core/base/shared` 中各 UI 版本演示站地址常量；
- 共享包中提到 element-plus / naive-ui 的注释与出处说明；`cspell.json` 中的相关单词；
- `playground` 中上游自带的演示路由与文案（发布前随 playground 一并删除）。

内部包名 `@vben/*`、`@vben-core/*` 一律不改（框架内部代码，改名后无法合并上游）。

## 合并上游更新

1. 在临时目录拉取上游的旧 tag 与新 tag（如 `v5.7.0` → `v5.x.y`），生成差异：

   ```bash
   git clone --filter=blob:none https://github.com/vbenjs/vue-vben-admin.git /tmp/vben && cd /tmp/vben
   # 共享部分（排除已删除的应用、文档与仓库级文件）
   git diff v5.7.0 v5.x.y --binary -- . \
     ':!apps/web-antd' ':!apps/web-ele' ':!apps/web-naive' ':!apps/web-tdesign' ':!apps/web-antdv-next' \
     ':!docs' ':!.github' ':!.changeset' ':!scripts/deploy' ':!scripts/turbo-run' ':!.vscode' \
     ':!internal/lint-configs/commitlint-config' ':!lefthook.yml' ':!.commitlintrc.js' ':!tea.yaml' \
     ':!.gitpod.yml' ':!.gitconfig' ':!vben-admin.code-workspace' ':!README*.md' ':!pnpm-lock.yaml' \
     > /tmp/vben-shared.patch
   # 应用部分（上游 apps/web-antdv-next 对应本仓库 apps/web）
   git diff v5.7.0 v5.x.y --binary -- apps/web-antdv-next > /tmp/vben-app.patch
   ```

2. 在本仓库根目录应用：

   ```bash
   git apply --reject --directory=web /tmp/vben-shared.patch
   git apply --reject -p3 --directory=web/apps/web /tmp/vben-app.patch
   ```

   处理 `*.rej`（通常是本目录改过的 `package.json`、`pnpm-workspace.yaml`、`apps/web` 中已改动的文件），然后在 `web/` 下 `pnpm install` 重新生成 `pnpm-lock.yaml`，跑一遍类型检查、lint、单测与构建。

3. 更新本文“上游版本”表。
