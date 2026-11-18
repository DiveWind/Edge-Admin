# CLAUDE.md（web/）

本脚手架的后台前端：Vben Admin 5（antdv-next 版）裁剪后的 pnpm + Turborepo monorepo，上游版本与裁剪记录见 [`README.md`](README.md)。后端是同仓库 `../server/`（Laravel 纯 API）。**两端只通过接口契约通信，契约以 [`../docs/backend-design.md`](../docs/backend-design.md) §6 为准**（响应包装 `{ code, data, message }`、认证与菜单接口、列表查询 `{ items, total }`），认证方式见 §7。不读、不依赖后端代码。

## 命令

在 `web/` 下执行，只用 pnpm（官方源 `https://registry.npmjs.org`，见 `.npmrc`）：

| 命令 | 说明 |
| --- | --- |
| `pnpm install` | 安装依赖；`postinstall` 自动构建内部包 stub |
| `pnpm dev` | 启动 `apps/web`（http://localhost:5999，`/api` 经 Vite 代理） |
| `pnpm build` / `pnpm preview` | 构建 / 预览 `apps/web`，产物在 `apps/web/dist` |
| `pnpm dev:play` / `pnpm build:play` | 启动 / 构建 `playground`（仅参考） |
| `pnpm check:type` | `vue-tsc` 类型检查 |
| `pnpm lint` / `pnpm format` | oxfmt + oxlint + ESLint + Stylelint 检查 / 自动修复 |
| `pnpm test:unit` | Vitest 单测（时区相关用例需 `TZ=Asia/Shanghai`，CI 已设置） |
| `pnpm test:e2e` | Playwright（目前只有 playground 的用例） |
| `pnpm check` | 循环依赖、未使用依赖、类型、拼写检查 |

**提交前必须通过**：`pnpm check:type && pnpm lint && pnpm test:unit && pnpm build`（CI：`../.github/workflows/web.yml`）。不使用 git 钩子，不要引入 lefthook / husky / commitlint。不要启动后台常驻的开发服务器去“验证”，用上面的检查与构建。

## 目录结构

```
apps/web/                 本项目的应用（包名 web；只在这里写业务代码）
  .env*                   标题、命名空间、端口、接口地址、mock 开关等
  vite.config.ts          开发代理
  src/
    adapter/              UI 库适配层（见下）：component/、form.ts、vxe-table.ts
    api/                  接口：request.ts（请求客户端与拦截器）、core/（登录、用户、菜单），业务接口按模块分文件
    layouts/              BasicLayout（顶栏、用户菜单、通知）与 AuthPageLayout
    locales/              应用级国际化：langs/{zh-CN,en-US}/*.json，dayjs 与 antdv-next 语言包切换
    router/               routes/core.ts（登录、404 等基础路由）、routes/modules/*.ts（静态菜单路由）、guard.ts、access.ts
    store/                应用级 store（auth：登录、登出、拉取用户信息）
    views/                页面；_core/ 为登录、个人中心、异常页等基础页面
    preferences.ts        覆盖 Vben 偏好设置（defineOverridesPreferences）
    bootstrap.ts / main.ts / app.vue
apps/backend-mock/        上游 Nitro mock（开发期参考，发布前删除）
playground/               上游功能演示（开发期参考，发布前删除）
packages/                 Vben 共享包 @vben/*、@vben-core/*（框架代码）
internal/                 vite / tsconfig / tailwind / lint 配置包
scripts/                  vsh（lint、check-dep、check-circular 等）与 clean.mjs
```

## 关键约定

### 框架代码不改名、尽量不改

- `packages/`、`internal/`、`scripts/` 是 Vben 框架代码。**`@vben/*`、`@vben-core/*` 包名一律不改**，改了就无法合并上游。
- 定制优先放在 `apps/web` 内完成：偏好设置（`preferences.ts`）、请求拦截（`api/request.ts`）、登录流程（`store/auth.ts`）、路由守卫（`router/guard.ts`）、组件适配（`adapter/`）。确需改共享包时，改动要小，并在 `README.md` 的“相对上游的裁剪与改动”中登记。
- 不手动改 `pnpm-lock.yaml`。新增依赖：版本写进 `pnpm-workspace.yaml` 的 `catalog`，`package.json` 里写 `"catalog:"`。

### UI：只用 Vben 体系 + antdv-next

不引入其他 UI 库、图标库或表格库；图标用 `@vben/icons`（Iconify / lucide），图表用 `@vben/plugins/echarts`。用法以 Vben 自身代码为准（`playground/src/views/system/**` 是最完整的 CRUD 示例）：

- **表单**：`useVbenForm`、`VbenFormSchema`、`z` 从 `#/adapter/form` 引入；schema 的 `component` 只能用 `adapter/component/index.ts` 中注册的名字（`Input`、`Select`、`ApiSelect`、`DatePicker`、`Upload`……）。需要新的表单组件时，先在适配层注册（`ComponentType`、`ComponentPropsMap`、`initComponentAdapter` 三处），不要在页面里绕过适配层。
- **表格**：`useVbenVxeGrid` 与相关类型从 `#/adapter/vxe-table` 引入；全局表格配置（分页字段 `items` / `total`、单元格渲染器）在该文件中统一设置。
- **弹窗 / 抽屉 / 页面容器**：`useVbenModal`、`useVbenDrawer`、`Page` 等从 `@vben/common-ui` 引入。
- **其他基础组件**：`Button`、`Card`、`Tag`、`Spin`、`Modal.confirm`、`message`、`notification` 等可直接从 `antdv-next` 引入（Vben 自己的页面也是这样用的）；需要统一默认行为的组件放进适配层。
- **权限控制**：按钮级用 `v-access:code="['权限码']"`、`<AccessControl :codes="['权限码']" type="code">` 或 `useAccess().hasAccessByCodes(['权限码'])`（`@vben/access`）；权限码来自 `GET /auth/codes`，以后端定义为准，不要自造。
- **文案**：一律走 `$t()`，写在 `src/locales/langs/{zh-CN,en-US}/*.json`，两种语言同时补齐。
- **样式**：优先 Tailwind 工具类与 antdv-next 组件属性；不写全局样式覆盖。

### 接口与认证

- 接口函数写在 `src/api/` 下（按模块分文件，用 `requestClient`），类型用 `namespace XxxApi` 声明，字段、路径、权限码严格按契约。
- `requestClient` 已按契约解包 `{ code, data, message }`（`code === 0` 为成功），页面拿到的就是 `data`。
- 当前 `apps/web` 仍使用上游 Nitro mock 与 Bearer token 流程；对接 Laravel 时按 §6.2 / §7 改为 Sanctum 会话 Cookie + Fortify，只改 `apps/web` 内的文件（`api/request.ts`、`api/core/*`、`store/auth.ts`、`router/guard.ts`、`preferences.ts`、`vite.config.ts`、`.env*`），共享包不改。
- 接口、字段、权限码有变动，先改 `../docs/backend-design.md`，再改代码。

### playground 只作参考

`playground/` 与 `apps/backend-mock/` 只用于开发期对照写法，不在其中开发业务，也不从 `apps/web` 引用它们的代码（需要时复制到 `apps/web`）。发布前删除。

### TypeScript

保持上游的 **TypeScript 6**（catalog 中 `typescript: ^6`）。TypeScript 7（原生版）目前不被 `vue-tsc` 支持，不要升级；`typescript`、`vue-tsc` 的版本随上游一起升级。
