# CLAUDE.md

## Project

Edge Admin 前端：RBAC 中后台脚手架，基于 Ant Design Pro v6（Umi Max 4 + antd 6 + ProComponents 3 + React 19）。
后端是同仓库 `../server/`（Laravel），两端**只通过 `../docs/architecture.md` 的 REST 契约通信**——字段、路径、权限码、
响应包络、排序白名单一律以契约为准，不读也不依赖后端代码，不使用 mock。

## Commands

`npm run dev` / `npm start`（localhost:8001，`/api`、`/storage` 代理到 127.0.0.1:8000），`npm run build`，`npm run preview`，
`npm run lint`（Biome lint + tsc），`npm run biome`（Biome 自动修复 + 格式化），`npm run tsc`，`npm test`（Vitest，node 环境），
`npx antd lint ./src`（antd 专项检查）。端口写在 `.env`（`PORT=8001`，已提交；个人覆盖写 `.env.local`）。

## Critical Rules

- **契约优先**：新增 / 修改接口前先改 `../docs/architecture.md`；契约不清楚就问，不要自己发明字段
- **UI 只用 Ant 体系**：`antd`、`@ant-design/icons`、`@ant-design/pro-components`、`@ant-design/plots`（图表）。不引入其他 UI / 图表 / 图标库，
  不手写 antd 已有的组件（表格、弹窗、下拉……）
- **Always `npx antd info <Component>` before writing antd code** —— antd 6 / ProComponents 3 改了不少 API（如 `Space orientation`、
  `Drawer size`、`Alert title`、`destroyOnHidden`、`Select showSearch={{ optionFilterProp }}`、`List` 已废弃改用 `Listy`），不要凭记忆
- **提交前必须通过**：`npm run lint`、`npx antd lint ./src`（零问题）、`npm test`、`npm run build`
- **Biome only** —— 不要引入 ESLint / Prettier
- **TypeScript strict**，禁止 `any`（Biome `noExplicitAny: error`）· **Node ≥ 22** · **npm + `package-lock.json`**
- **`src/.umi` 是自动生成的** —— 开发服务器异常时删掉 `src/.umi` 重启；`npm install` 会通过 postinstall 执行 `max setup`

## Architecture

```
config/
  config.ts             # Umi 配置（插件开关、locale、proxy 选择）
  routes.ts             # 声明式路由 = 菜单 + 路由级权限
  defaultSettings.ts    # ProLayout 设置（标题、logo、布局），在 src/app.tsx 的 layout 中展开
  proxy.ts              # 开发代理
src/
  app.tsx               # 运行时配置：getInitialState（GET /api/auth/me）、layout、request、innerProvider
  access.ts             # 权限（见下）
  requestErrorConfig.ts # 请求拦截（Bearer Token）+ Laravel 错误处理
  components/           # 跨页面公共组件（DeleteAction、ImageUpload、FeedbackBridge、RightContent/*、ErrorBoundary...）
  hooks/useValueEnums.ts# 共用 valueEnum（启停状态、发布状态、数据权限）
  services/             # 跨页面接口：auth、options、upload、notification、permission；typings.d.ts 为全局 API 类型
  utils/                # auth（token / 登录跳转）、table（ProTable ↔ 契约参数）、form（422 回填）、error、i18n、tree
  locales/<lang>.ts + <lang>/{app,common,component,menu}.ts + <lang>/pages/*.ts
  pages/<模块>/<页面>/   # index.tsx + service.ts + data.d.ts (+ components/)
```

**State**：`useModel('@@initialState')` 取 `currentUser`；ProTable `request` 负责列表加载；其他服务端状态用 `@tanstack/react-query`
（如通知未读数，query key 定义在 service 里，变更后 `invalidateQueries`）。

**Styling priority**：antd 组件属性与布局组件（`Flex`/`Space`/`Row`/`Col`/`ProCard`）→ `antd-style` `createStyles`（需要主题 token 时）→
Tailwind（仅在不得已时做少量布局适配）。不写全局 Less（`src/global.less` 只保留最基础的 reset）。

## 本项目约定

### 请求层

- 统一用 `import { request } from '@umijs/max'`，页面接口写在页面目录的 `service.ts`，类型写在 `data.d.ts`；
  跨页面接口放 `src/services/`，全局类型在 `src/services/typings.d.ts`（`API.Resource<T>`、`API.Paginated<T>`、`API.Collection<T>`、
  `API.CurrentUser`、`API.ListQuery`……）。
- 请求拦截器自动带 `Authorization: Bearer <token>`、`Accept: application/json`；token 只通过 `@/utils/auth` 读写。
- 错误统一在 `requestErrorConfig.ts` 处理：401 清 token 并跳登录（带 redirect）；403 / 422 / 429 / 其他显示后端 `message`。
  记录级业务规则拒绝（不能删自己、有子部门不可删……）也是 403 + message，只提示，不跳 403 页。
  单个请求需要自行处理时传 `skipErrorHandler: true`。
- 表单提交用 `submitForm(formRef.current, () => save(values))`：成功返回 `true`（关闭弹窗），422 时把 `errors` 回填到字段并返回 `false`。
- 组件外需要提示时用 `getMessageApi()`（`App.useApp()` 的实例，由 `FeedbackBridge` 注入），不要用 antd 静态 `message.xxx`。

### 列表页

- ProTable：`request={tableRequest(queryXxx)}`。`tableRequest` 把 `{current, pageSize, ...filters}` + sorter 转成契约的
  `page / per_page / sort`，并把 Laravel 分页响应转成 `{data, total, success}`。不分页的列表（树、定时任务、失败队列）
  直接在 `request` 里返回 `{ data, success: true }` 并设 `pagination={false}`。
- **只对契约 5.7 白名单内的字段开 `sorter: true`**（非白名单后端返回 422）。
- 日期区间搜索：隐藏列 `valueType: 'dateRange'` + `search: { transform: createdRangeTransform }`。
- 删除用 `<DeleteAction onConfirm={() => deleteXxx(id)} onSuccess={reload} />`；状态用 `valueEnum`；`dateTime` 列给 `width: 170` 防止折行。

### 表单弹窗

- `ModalForm` / `DrawerForm` + `modalProps={{ destroyOnHidden: true }}`（或 `drawerProps`），**传 `formRef` 不传 `form`**——
  表单 store 随弹层销毁，每次打开都是干净的（传外部 `form` 会把上一次的值带进下一次）；编辑时用 `request` 拉详情回填。
- `omitNil={false}`，可清空的下拉字段在提交时把 `undefined` 转成 `null`，否则"清空"不会提交到后端。

### 权限

- 权限码以 `/api/auth/me` 的 `permissions` 为唯一来源（含 `system`、`system.user` 这类菜单级码）；`src/access.ts` 中的
  `PermissionCode` 只是类型，防止拼写错误，新增后端权限点时同步追加。
- **路由级**：`config/routes.ts` 写 `access: 'routeFilter'` + `permission: 'xxx.view'`（或数组，满足其一）。无权限菜单隐藏、
  直接访问渲染 403。分组路由不写 access，也不要在分组里放没有权限声明的 redirect 子路由（会让分组永远可见）。
  不把权限码直接铺成 access 的 key：Umi access 插件对未声明 `access` 的路由默认可访问，用单一过滤函数 + 路由自定义字段
  更不容易漏，也不需要维护"权限码 → 布尔值"的映射。
- **按钮级**：`const access = useAccess(); access.can('system.user.create') && <Button />`，或 `<Access accessible={...}>`。
  前端隐藏只是体验，真正的校验在后端。

### i18n

- 只有 `zh-CN`（默认）与 `en-US`。路由 `name` → `menu.<父 name>.<name>`；页面文案按模块放 `locales/<lang>/pages/<模块>.ts`。
- 组件内 `const t = useT(); t('common.create')`，组件外 `t()`（`@/utils/i18n`）。key 类型来自 zh-CN，en-US 各模块用
  `Record<keyof typeof zh, string>` 约束，拼错 key 或漏翻都会在 `tsc` 阶段报错。

### 新增一个业务页面（清单）

1. 契约：在 `../docs/architecture.md` 补接口、权限码与排序白名单（后端同步实现）。
2. `src/access.ts`：`PermissionCode` 追加新权限码。
3. `src/pages/<模块>/<页面>/`：`data.d.ts`（资源类型 + 表单值类型）、`service.ts`（`request` 调用）、`index.tsx`
   （`PageContainer` + `ProTable`）、`components/XxxForm.tsx`（`ModalForm`，参考 `content/categories`）。
   带数据权限 / 详情回填 / 上传的完整示例看 `content/articles`。
4. `config/routes.ts`：加路由，`access: 'routeFilter'` + `permission`。
5. `src/locales/{zh-CN,en-US}/menu.ts` 加菜单名，`pages/<模块>.ts` 加页面文案。
6. 跑 `npm run lint && npx antd lint ./src && npm test && npm run build`。

## AI Skills

`.claude/skills/` 内置两个 Skill：`/antd`（`@ant-design/cli`，离线查 API / demo / lint，写 antd 代码前必用）与 `/pro-upgrade`
（对比上游 Ant Design Pro 模板合并框架更新；本项目已大幅偏离模板，使用时务必保守、逐项确认）。
`docs/cheatsheet.zh-CN.md` 为上游 Ant Design Pro 速查，仅作参考，与本文冲突时以本文为准。

## Behavioral guidelines

- **Think before coding**：不确定就问，存在多种理解时说出来，不要默默选一种。
- **Simplicity first**：只写需要的代码，不做投机性的抽象和配置项。
- **Surgical changes**：只改与需求相关的代码，清理自己造成的无用代码，不顺手"优化"无关代码。
- **Goal-driven**：先定义可验证的完成标准（测试 / lint / build），循环直到通过。
