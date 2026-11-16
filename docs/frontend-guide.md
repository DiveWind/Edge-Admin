# 前端开发规范

Edge Admin 前端位于 `web/`，基于 Ant Design Pro v6（Umi Max 4 + antd 6 + ProComponents 3 + React 19）。
前后端**只通过 [architecture.md](architecture.md) 的 REST 契约通信**：字段、路径、权限码、排序白名单都以契约为准，前端不读后端代码、不使用 mock。
面向 AI 助手的精简版约定见 [`web/CLAUDE.md`](../web/CLAUDE.md)。

## 1. 技术栈与硬性约束

- UI 只用 Ant 体系：`antd`、`@ant-design/icons`、`@ant-design/pro-components`、`@ant-design/plots`（图表）。不引入其他 UI / 图表 / 图标库，不手写 antd 已有的组件。
- 写 antd 代码前先 `npx antd info <Component>` 查 API（antd 6 / ProComponents 3 有大量变更：`Drawer size`、`Alert title`、`destroyOnHidden`、`Select showSearch={{ optionFilterProp }}`、`Listy`……）。
- 样式优先级：组件属性与布局组件（`Flex` / `Space` / `Row` / `Col` / `ProCard`）→ `antd-style` 的 `createStyles`（需要主题 token 时）→ Tailwind（仅少量布局适配）。**不写死颜色**，一律用 token（暗色主题依赖它）。
- 代码规范只用 Biome（无 ESLint / Prettier），TypeScript strict，禁止 `any`；Node ≥ 22，npm + `package-lock.json`。
- 提交前必须通过：`npm run lint`、`npx antd lint ./src`（零问题）、`npm test`、`npm run build`。

## 2. 目录与分层

```
web/
  config/
    config.ts            # Umi 配置（插件、locale、代理）
    routes.ts            # 声明式路由 = 菜单 + 路由级权限（含生成器锚点）
    defaultSettings.ts   # ProLayout 默认设置
  e2e/                   # Playwright 端到端用例（npm run e2e）
  src/
    app.tsx              # 运行时：getInitialState（当前用户 + 公开参数 + 主题）、layout、antd、request、innerProvider
    access.ts            # 权限：PermissionCode 类型 + routeFilter / can
    requestErrorConfig.ts# 请求拦截（Token、Accept-Language）与统一错误处理
    components/          # 跨页面组件：DeleteAction、DictTag、ImageUpload、GlobalStyle、RightContent/*（通知、主题、语言、头像）...
    hooks/               # useValueEnums、useDict、usePermissionName、useThemeMode
    services/            # 跨页面接口（auth、options、dict、upload、notification、permission）与全局类型 typings.d.ts
    utils/               # 纯函数：table、form、dict、permission、settings、theme、format、tree、route、error、i18n、auth
    locales/             # zh-CN（默认，key 类型来源）/ en-US
    pages/<模块>/<页面>/  # index.tsx + service.ts + data.d.ts (+ components/)
```

- **页面 co-location**：页面私有的接口、类型、组件、hook 都放在页面目录；被两个以上页面使用才提到 `src/services`、`src/components`、`src/hooks`。
- **utils 只放纯函数**（可在 node 环境单测）；用到 React / Umi 运行时的放 hooks 或 components。

## 3. 请求层与错误处理

- 统一 `import { request } from '@umijs/max'`。页面接口写在页面的 `service.ts`，返回类型用全局的 `API.Resource<T>` / `API.Paginated<T>` / `API.Collection<T>`。
- 请求拦截器（`requestErrorConfig.ts`）自动附加：
  - `Authorization: Bearer <token>`（token 只经 `@/utils/auth` 读写）；
  - `Accept: application/json`；
  - `Accept-Language: <当前界面语言>`，后端据此返回对应语言的 `message` / `errors`，切换语言后立即生效。
- 错误统一处理：`401` 清 token 并跳登录（带 `redirect`）；`403` / `422` / `429` / 其他显示后端 `message`。
  记录级业务规则拒绝（不能删自己、存在字典项时不能删字典……）也是 `403 + message`，只提示，不跳 403 页。
- 单个请求要自己处理错误时传 `skipErrorHandler: true`。
- 组件外需要提示用 `getMessageApi()`，不要用 antd 静态 `message.xxx`（拿不到主题与语言）。

## 4. 权限

- 权限码唯一来源是 `GET /api/auth/me` 的 `permissions`（含菜单级码）。`src/access.ts` 的 `PermissionCode` 只是类型，后端新增权限点时同步追加（生成器会自动追加）。
- **路由级**：`config/routes.ts` 中 `access: 'routeFilter'` + `permission: 'xxx.view'`（或数组，满足其一）。无权限时菜单隐藏，直接访问渲染 403。
  分组路由不写 `access`；不要在分组里放没有权限声明的 redirect。
- **按钮级**：`const access = useAccess(); access.can('system.dict.create') && <Button />`。前端隐藏只是体验，真正的校验在后端。
- **权限名称 i18n**：后端约定菜单 / 权限点名称由前端按 `code` 翻译，后端 `name` 兜底。查找顺序：
  `permission.<code>` → 操作类 `permission.action.<最后一段>`（view / create / update / delete / publish……）→ 后端 `name`。
  文案在 `locales/<lang>/permission.ts`；角色授权树、权限管理页用 `usePermissionTranslator()` 翻译整棵树。新增模块时补菜单级 `permission.<code>` 即可。

## 5. 字典与系统参数

### 5.1 数据字典

```tsx
import { DictTag } from '@/components';
import { useDict, useDicts } from '@/hooks/useDict';

const source = useDict('article_source');
// 或批量：const { article_source, common_yes_no } = useDicts(['article_source', 'common_yes_no']);

// 列：valueEnum 用于搜索下拉，DictTag 按字典颜色渲染
{ dataIndex: 'source', valueType: 'select', valueEnum: source.valueEnum,
  render: (_, r) => <DictTag code="article_source" value={r.source} /> }

// 表单
<ProFormSelect name="source" options={source.options} />
```

- `useDict` 基于 react-query：每个 code 独立缓存（10 分钟）、相同 code 自动去重，同一时刻请求的多个 code 合并为一次 `GET /api/options/dicts?codes=a,b`。
- 返回 `{ options, valueEnum, find, loading }`。`valueEnum` 是保持后端排序的 `Map`；字典项 `color` 为语义色（`success` / `processing` / `error` / `warning` / `default`）时映射为 Badge `status`，其余（`green`、`#722ed1`）作为 `color`。
- 字典在本端增删改后调用 `queryClient.invalidateQueries({ queryKey: dictKeys.all })`，所有使用中的字典自动刷新（数据字典页已处理）。
- 业务对接示范：文章的 `source` 字段（`pages/content/articles`）。

### 5.2 公共系统参数

- `getInitialState` 并行拉取 `GET /api/options/settings`（无需登录），转换为 `initialState.site`：
  `site.name` → 布局标题、登录页标题、`document.title`；`site.login_notice` → 登录页公告（有值才显示）；`site.icp` → 页脚备案号。
- 接口失败或值为空时回退到 `config/defaultSettings.ts` 的默认值（`utils/settings.ts`）。
- 系统参数页保存后调用 `initialState.fetchSiteSettings()` 刷新，站点名称立即生效。
- 新增需要在前端使用的公开参数：后端设置 `is_public`，前端在 `utils/settings.ts` 的 `SiteSettings` / `toSiteSettings` 中加字段并补单测。

## 6. 列表页规范

```tsx
<ProTable<Article>
  rowKey="id"
  actionRef={actionRef}
  columns={columns}
  request={tableRequest(queryArticles)}
  columnsState={{ defaultValue: { created_at: { show: false } } }}
  toolBarRender={() => [access.can('content.article.create') && <Button ... />]}
/>
```

- `tableRequest` 把 `{current, pageSize, ...filters}` + sorter 转成契约的 `page / per_page / sort`，并把分页响应转成 ProTable 结果。不分页的列表在 `request` 里直接返回 `{ data, success: true }` 并设 `pagination={false}`。
- **排序白名单**：只对契约 5.7 列出的字段开 `sorter: true`，否则后端返回 422。
- **valueEnum**：启停状态 `useStatusEnum()`、发布状态 `usePublishStatusEnum()`、数据权限 `useDataScopeEnum()`、布尔 `useBooleanEnum()`（搜索时配合 `search: { transform: booleanTransform('field') }` 转 1 / 0）、字典 `useDict(code).valueEnum`。
- **列宽**：`dateTime` 列 170，人名列 120；`ellipsis` 列必须给 `width`。列多时用 `columnsState` 默认隐藏次要列（列要写 `key`），保证 1440 宽下常用列完整显示且不出现横向溢出。
  `scroll={{ x: 'max-content' }}` 只用在没有 `ellipsis` 列的表格上；窄屏卡片内的小表格可给固定 `scroll={{ x: 520 }}`。
- 日期区间搜索：隐藏列 `valueType: 'dateRange'` + `search: { transform: createdRangeTransform }`。
- 删除：`<DeleteAction onConfirm={() => deleteXxx(id)} onSuccess={reload} />`（可传 `title`、`successMessage`、`disabled`、自定义文字）。
- 树形数据交给表格前用 `pruneEmptyChildren` 去掉空 `children`。
- 字节数用 `formatBytes`（`utils/format.ts`）显示。

## 7. 表单规范

```tsx
const formRef = useRef<ProFormInstance<XxxFormValues>>(undefined);

<ModalForm<XxxFormValues>
  formRef={formRef}                       // 传 formRef，不传 form
  modalProps={{ destroyOnHidden: true }}  // 弹层销毁后表单是干净的
  omitNil={false}
  initialValues={{ status: 'active' }}
  request={record ? async () => (await getXxx(record.id)).data : undefined}  // 编辑时拉详情
  onValuesChange={(changed) => clearFieldErrors(formRef.current, changed)}  // 有无 rules 的字段时加上
  onFinish={(values) => submitForm(formRef.current, () => save(values))}
/>
```

- `submitForm`：成功返回 `true`（关闭弹窗）；422 时把 `errors` 回填到字段并返回 `false`；提示由全局错误处理负责。
- 回填的错误只在字段重新校验时消失；没有前端 `rules` 的字段输入时不会校验，用 `clearFieldErrors` 清除。
- 可清空的下拉在提交时把 `undefined` 转成 `null`，否则"清空"不会提交到后端。
- JSON 字段：`ProFormTextArea` + `rules={[jsonRule(t('common.jsonInvalid'))]}` + `convertValue={stringifyJson}` + `transform`（`parseJson`）。
- **日期时间**：提交带时区偏移的 ISO 8601（契约第 2 节），`ProFormDateTimePicker` 加 `transform={(value: unknown) => ({ field: toOffsetDateTime(value) })}`
  （`utils/form.ts`）；显示仍是浏览器本地时间（`valueType: 'dateTime'` 列、日期控件按 dayjs 解析带偏移的值）。纯日期（`date` 字段、
  `created_from` / `created_to`）按日历日提交 `YYYY-MM-DD`。
- 外键下拉：`request={() => fetchSelectOptions('article-categories')}`（`services/options.ts`，通用的 `GET /api/options/<资源>` → `{label, value}`，
  树形选项展开）；已有专用函数时也可 `getXxxOptions().then(toSelectOptions)`。
- 上传：`ImageUpload`（单图，值为 url）；`uploadFile()` 返回完整 `Attachment`（`id`、`name`、`mime`、`size`、`url`……），上传的文件会出现在附件管理中。

## 8. i18n

- 只有 `zh-CN`（默认）与 `en-US`。路由 `name` → `menu.<父 name>.<name>`；页面文案按模块放 `locales/<lang>/pages/<模块>.ts`；权限名称在 `locales/<lang>/permission.ts`。
- 组件内 `const t = useT(); t('common.create')`，组件外 `t()`。key 类型来自 zh-CN，en-US 用 `Record<keyof typeof zh, string>` 约束，拼错或漏翻会在 `tsc` 报错。
- 数据（字典 label、系统参数、后端 `name`）不做多语言；后端提示文案随 `Accept-Language` 切换。
- 不要删除 `locales/<lang>.ts` 与 `menu.ts` 中的 `// edge:*` 锚点。

## 9. 主题

- 右上角（以及登录页）的明 / 暗切换：`components/RightContent/ThemeSwitch.tsx`。
- 实现：全局 antd 算法由 Umi antd 插件管理——`app.tsx` 的 `antd` 运行时配置按 localStorage（`edge_admin_theme`）设置初始 `theme.algorithm`，
  切换时用 `useAntdConfigSetter()` 更新；布局用 ProLayout `navTheme`（`realDark`），取自 `initialState.theme`。
- 页面背景由 `GlobalStyle` 用 `colorBgLayout` 设置到 `body`。
- 自定义样式一律用 token（`createStyles(({ token, css }) => ...)` 或 `theme.useToken()`）；图表通过 `ChartBox` 自动传入 G2 主题（`classic` / `classicDark`），新增图表放在 `ChartBox` 里即可；
  其他需要感知主题的地方用 `useThemeMode()`。

## 10. 新增页面

### 10.1 手写

1. 契约：在 `docs/architecture.md` 补接口、权限码、排序白名单（后端同步实现）。
2. `src/access.ts`：`PermissionCode` 追加权限码（锚点之前）。
3. `src/pages/<模块>/<页面>/`：`data.d.ts`、`service.ts`、`index.tsx`（`PageContainer` + `ProTable`）、`components/XxxForm.tsx`。
   参考：标准 CRUD `content/categories`；数据权限 / 详情回填 / 上传 / 字典 `content/articles`；主从（抽屉）`system/dicts`；按类型切换控件 `system/settings`。
4. `config/routes.ts`：加路由（`access: 'routeFilter'` + `permission`）。
5. `locales/{zh-CN,en-US}/menu.ts` 加菜单名，`pages/<模块>.ts` 加页面文案，`permission.ts` 加菜单级权限名称。
6. `npm run lint && npx antd lint ./src && npm test && npm run build`，并补 e2e 冒烟。

### 10.2 用生成器

```bash
cd server && php artisan edge:make-module Tag --module=content --with-options \
  --fields="name:string:required|max:50,color:string:nullable,sort:integer,status:status"
php artisan permission:sync && php artisan migrate
```

生成器写好列表页、`components/<Class>Form.tsx`、service、类型、双语文案（导入按需生成、带与后端一致的必填 / 长度校验、外键下拉接入选项接口、
日期时间按上面的约定提交），登记路由、菜单、权限码，并在 `node_modules` 存在时用 Biome 格式化，生成后直接通过 lint / build。
之后的完善（中文文案、列宽、e2e 冒烟）与模板定制方法见 [generator.md](generator.md) 第 6 节。

## 11. 测试

| 命令 | 内容 |
|---|---|
| `npm test` | Vitest 单元测试（node 环境，`src/**/*.test.ts`）：纯逻辑——权限、列表参数转换、表单工具、字典转换与批量加载、权限名称回退、公开参数、格式化…… |
| `npm run e2e` | Playwright 端到端（`e2e/*.spec.ts`），**需要真实后端**，不在默认 `npm test` 与 CI 必过项中 |

e2e 前置条件：

```bash
cd server && php artisan migrate:fresh --seed && php artisan serve --port=8000   # 终端 1
cd server && php artisan queue:work                                               # 终端 2（公告通知用例需要）
cd web && npm run e2e        # 未运行 dev server 时自动启动；E2E_BASE_URL 可指向其他地址
```

- 覆盖：登录 / 登出与 redirect、各角色菜单收敛与 403、每个模块冒烟、岗位完整 CRUD（含 422 回填）、公告 → 通知流程、
  字典（含缓存失效）、系统参数、附件、在线会话、系统信息、文章来源、工作台、暗色主题、英文界面下的后端文案与权限名称。
- `global-setup.ts` 用 API 为每个角色登录一次并保存 `storageState`（复用登录态，用例更快；登录接口按"用户名 + IP"只限制失败次数，成功登录不计数）。
- 用例写入的数据带随机后缀并在用例内清理；建议每次运行前 `migrate:fresh --seed`。浏览器使用预装的 Chromium（`PLAYWRIGHT_BROWSERS_PATH`），
  `@playwright/test` 版本需与之匹配，不要随意升级或执行 `playwright install`。
- 开发服务器运行时执行 `npm run build` 可能让开发服务器开始返回 HTML 代替脚本（utoopack 缓存冲突），遇到时停掉所有 dev 进程后重启。
