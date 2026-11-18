# CLAUDE.md（web/）

本脚手架的后台前端：Vben Admin 5（antdv-next 版）裁剪后的 pnpm + Turborepo monorepo，上游版本与裁剪记录见 [`README.md`](README.md)。后端是同仓库 `../server/`（Laravel 纯 API）。**两端只通过接口契约通信，契约以 [`../docs/backend-design.md`](../docs/backend-design.md) §6 为准**（响应包装 `{ code, data, message }`、认证与菜单接口、列表查询 `{ items, total }`），认证方式见 §7。不读、不依赖后端代码。

## 命令

在 `web/` 下执行，只用 pnpm（官方源 `https://registry.npmjs.org`，见 `.npmrc`）：

| 命令 | 说明 |
| --- | --- |
| `pnpm install` | 安装依赖；`postinstall` 自动构建内部包 stub |
| `pnpm dev` | 启动 `apps/web`（http://localhost:5999，`/api` 经 Vite 代理到 `http://localhost:8000`，即 `php artisan serve`） |
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
    api/                  接口：client.ts（请求客户端与拦截器）、request.ts（注入 store 与界面的 requestClient）、core/（认证、用户、两步验证、菜单），业务接口按模块分文件
    layouts/              BasicLayout（顶栏、用户菜单、通知）与 AuthPageLayout
    locales/              应用级国际化：langs/{zh-CN,en-US}/*.json，dayjs 与 antdv-next 语言包切换
    router/               routes/core.ts（登录、404 等基础路由）、routes/modules/*.ts（静态路由：工作台、个人中心）、guard.ts、access.ts（生成路由与菜单）
    store/                应用级 store（auth：登录、登出、拉取用户信息与权限码）
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
- 定制优先放在 `apps/web` 内完成：偏好设置（`preferences.ts`）、请求拦截（`api/client.ts`、`api/request.ts`）、登录流程（`store/auth.ts`）、路由守卫（`router/guard.ts`）、组件适配（`adapter/`）。确需改共享包时，改动要小，并在 `README.md` 的“相对上游的裁剪与改动”中登记。
- 不手动改 `pnpm-lock.yaml`。新增依赖：版本写进 `pnpm-workspace.yaml` 的 `catalog`，`package.json` 里写 `"catalog:"`。

### UI：只用 Vben 体系 + antdv-next

不引入其他 UI 库、图标库或表格库；图标用 `@vben/icons`（Iconify / lucide），图表用 `@vben/plugins/echarts`。用法以 Vben 自身代码为准（`playground/src/views/system/**` 是最完整的 CRUD 示例）：

- **表单**：`useVbenForm`、`VbenFormSchema`、`z` 从 `#/adapter/form` 引入；schema 的 `component` 只能用 `adapter/component/index.ts` 中注册的名字（`Input`、`Select`、`ApiSelect`、`DatePicker`、`Upload`……）。需要新的表单组件时，先在适配层注册（`ComponentType`、`ComponentPropsMap`、`initComponentAdapter` 三处），不要在页面里绕过适配层。
- **表格**：`useVbenVxeGrid` 与相关类型从 `#/adapter/vxe-table` 引入；全局表格配置（分页字段 `items` / `total`、单元格渲染器）在该文件中统一设置。
- **弹窗 / 抽屉 / 页面容器**：`useVbenModal`、`useVbenDrawer`、`Page` 等从 `@vben/common-ui` 引入。
- **其他基础组件**：`Button`、`Card`、`Tag`、`Spin`、`Modal.confirm`、`message`、`notification` 等可直接从 `antdv-next` 引入（Vben 自己的页面也是这样用的）；需要统一默认行为的组件放进适配层。
- **表格单元格渲染器**（`adapter/vxe-table.ts`）：`CellTag`、`CellSwitch` 默认按状态 `'enabled'` / `'disabled'` 显示与切换（§6.4）；`CellOperation` 为操作列，`attrs.onClick({ code, row })`（类型 `OnActionClickFn`），按钮属性可写成 `(row) => 值`（`show`、`disabled`），`code: 'delete'` 自带确认框。
- **权限控制**：见下文“菜单与按钮权限”。
- **文案**：一律走 `$t()`，写在 `src/locales/langs/{zh-CN,en-US}/*.json`，两种语言同时补齐。
- **样式**：优先 Tailwind 工具类与 antdv-next 组件属性；不写全局样式覆盖。

### 接口与认证

- 接口函数写在 `src/api/` 下（按模块分文件，用 `requestClient`），类型用 `namespace XxxApi` 声明，字段、路径、权限码严格按契约。
- `requestClient` 已按契约解包 `{ code, data, message }`（`code === 0` 为成功），页面拿到的就是 `data`；请求头 `Accept-Language` 为当前界面语言（`zh-CN` / `en-US`），后端的提示文案随之切换（§6.1）。
- 认证为 Sanctum 会话 Cookie + Fortify（§6.2 / §7）：请求携带 Cookie 与 `X-XSRF-TOKEN`，没有 Authorization 头与刷新令牌；419 重新获取 CSRF Cookie 后重试一次，423 弹出确认密码框后重试，401 清除已登录标记回登录页（`api/client.ts`）。“已登录标记”是 access store 的 `accessToken` 字段中的固定值（`store/auth.ts` 的 `SESSION_MARKER`），启动时用 `GET /user/info` 确认会话（`router/guard.ts`）。
- 422 的字段错误用 `setFormErrors(formApi, getValidationErrors(error))` 回填到表单（表单字段名与接口字段名一致）；调用方自行处理的状态码用 `AppRequestConfig` 的 `silentError` 关闭自动提示。
- 列表页：查询参数用 `toListParams(page, formValues)`（`api/list.ts`）生成 `{ page, pageSize, filter }`，空值自动去掉，数组（时间区间、多选）合并为 `起,止`；序列化为 `filter[字段]=值`（§6.3）。返回值类型 `ListApi.Result<T>`（`{ items, total }`）。
- 接口、字段、权限码有变动，先改 `../docs/backend-design.md`，再改代码。

### 菜单与按钮权限

- 路由模式为 `mixed`（`preferences.ts`，§6.2）：`routes/modules/*.ts` 只放所有登录用户都有的页面（工作台、个人中心），不写 `meta.authority`；其余菜单全部由 `GET /menu/all` 下发，与静态路由按 `name` 合并（`router/access.ts`）。不要为后端菜单再写静态路由。
- **页面文件路径与后端菜单节点的 `component` 一一对应**：`component: '/system/user/list'` 即 `src/views/system/user/list.vue`；找不到文件时显示 404 页。页面私有组件放在页面目录的 `modules/` 下（不会出现在菜单管理的组件列表 `componentKeys` 中）。
- 菜单标题 `meta.title` 是 i18n key（如 `system.user.title`），后端内置节点的 key（含按钮标题）写在 `src/locales/langs/*/system.json` 等文件中；Vben 自带的 `common.create` 等不重复定义。
- **权限码就是后端节点的 `name`**（`system.user`、`system.user.create`……，见 §6.2、§6.5），以后端定义为准，不要自造。`GET /auth/codes` 在登录时与启动时（拿到 `/user/info` 之后）重新拉取，登出时清空（`store/auth.ts`、`router/guard.ts`）。
- 按钮权限写法：
  - 普通按钮：`<Button v-access:code="'system.user.create'">`（多个码满足其一用数组）；
  - 表格操作列：`CellOperation` 的 `show` 配合 `useAccess().hasAccessByCodes`：

    ```ts
    const { hasAccessByCodes } = useAccess();
    // columns 中
    {
      cellRender: {
        attrs: { nameField: 'username', onClick: onActionClick },
        name: 'CellOperation',
        options: [
          { code: 'edit', show: () => hasAccessByCodes(['system.user.update']) },
          { code: 'delete', show: () => hasAccessByCodes(['system.user.delete']) },
        ],
      },
      field: 'operation',
      fixed: 'right',
      title: $t('system.operation'),
    }
    ```

  - 需要整块隐藏时用 `<AccessControl :codes="['…']" type="code">`（`@vben/access`）。

### playground 只作参考

`playground/` 与 `apps/backend-mock/` 只用于开发期对照写法，不在其中开发业务，也不从 `apps/web` 引用它们的代码（需要时复制到 `apps/web`）。发布前删除。

### TypeScript

保持上游的 **TypeScript 6**（catalog 中 `typescript: ^6`）。TypeScript 7（原生版）目前不被 `vue-tsc` 支持，不要升级；`typescript`、`vue-tsc` 的版本随上游一起升级。
