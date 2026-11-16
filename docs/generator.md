# 代码生成器 `edge:make-module`

一条命令生成一个符合本项目规范的单表 CRUD 业务模块（后端全套 + 前端列表 / 表单页面），并**幂等地**登记权限点、路由、操作日志摘要与前端路由 / 菜单 / 文案。
生成物只是起点：多步写入、副作用、记录级规则等仍按 [backend-guide.md](backend-guide.md) 手工补充。

```bash
cd server
php artisan edge:make-module Tag --module=content \
  --fields="name:string:required|max:50,color:string:nullable,sort:integer,status:status"

php artisan migrate
php artisan permission:sync
php artisan test --filter=TagTest
composer lint && composer analyse

cd ../web   # 前端收尾，见 6.3
npx biome check --write --unsafe src/pages/content/tags src/locales src/access.ts config/routes.ts
npm run lint && npx antd lint ./src && npm run build
```

## 1. 参数

| 参数 / 选项 | 说明 |
|---|---|
| `name` | 模型名，单数 StudlyCase（`Tag`、`ArticleTag`）。表名 / 路由 / 权限码由它推导，见第 3 节 |
| `--module=` | **必填**。所属模块（小写字母、数字、`-`），决定权限码前缀、控制器 / 请求命名空间、前端菜单分组，如 `content` |
| `--fields=` | **必填**。字段定义，见第 2 节 |
| `--data-scope` | 接入数据权限：模型 `use HasDataScope`，迁移加 `department_id` / `created_by`，控制器 `visibleTo()`，Policy `isVisibleTo()` |
| `--soft-deletes` | 迁移 `softDeletes()`，模型 `use SoftDeletes` |
| `--no-web` | 不生成前端代码 |
| `--force` | 覆盖已存在的文件（登记类修改始终幂等，不会重复） |
| `--dry-run` | 只打印将要创建 / 修改的文件与需手动处理的事项，不写入 |

## 2. 字段 DSL

`name:type[:参数][:rules]`，多个字段用逗号分隔。

- `rules` 用 `|` 分隔，原样透传给 Laravel 校验，可以包含 `:` 和 `,`（如 `in:low,high`）；只有"逗号后紧跟 `字段名:`"才会被当作新字段。
- 未声明 `required` / `nullable` / `sometimes` / `present` / `filled` 时默认 `required`；声明了 `nullable` 时迁移列可空。
- `string` 的 `max:N`（≤255）同时决定迁移列长度；未声明时追加 `max:255`。
- `id`、`created_at`、`updated_at`、`deleted_at`、`department_id`、`created_by` 由生成器维护，不能声明。

| type | 迁移 | cast | 校验（类型部分） | 列表过滤 | TS 类型 / valueType |
|---|---|---|---|---|---|
| `string` | `string(N)` | — | `string` `max:255` | 模糊 | `string` / `text` |
| `text` | `text` | — | `string` | — | `string` / `textarea` |
| `integer` | `integer` | `integer` | `integer` | 精确 | `number` / `digit` |
| `decimal` | `decimal(10,2)` | `decimal:2` | `numeric` | — | `string` / `digit` |
| `boolean` | `boolean` 默认 false | `boolean` | `boolean` | 精确 | `boolean` / `switch` |
| `date` | `date` | `date` | `date` | 按日 | `string` / `date` |
| `datetime` | `dateTime` | `datetime` | `date` | — | `string` / `dateTime` |
| `json` | `json` | `array` | `array` | — | `Record<string, unknown>` / `jsonCode` |
| `status` | `string(20)` 默认 active | `Status::class` | `Rule::enum(Status::class)` | 精确 | `API.Status` / `select` |
| `foreign:<表>` | `foreignId()->constrained('<表>')` | `integer` | `integer` `Rule::exists('<表>', 'id')` | 精确 | `number` / `digit` |
| `dict:<编码>` | `string(64)` 带索引 | — | `string` `new InDict('<编码>')` | 精确 | `string` / `text` |

`foreign:<表>` 会按表名推导关联模型（`article_categories` → `ArticleCategory`），生成 `belongsTo` 关系与 Factory 关联；模型需已存在。
`dict:<编码>` 生成的测试会自动准备该字典与一个值为 `sample` 的字典项。

## 3. 生成内容

以 `Tag --module=content` 为例：

| 类别 | 路径 / 内容 |
|---|---|
| 迁移 | `database/migrations/<时间>_create_tags_table.php`（已存在同名迁移时复用其文件名） |
| 模型 / 工厂 | `app/Models/Tag.php`（`#[Fillable]`、`casts()`、关系、`#[Scope] filter()`）、`database/factories/TagFactory.php` |
| 请求 | `app/Http/Requests/Content/{TagIndexRequest,StoreTagRequest,UpdateTagRequest}.php`（Index 继承 `ListRequest`；Update 在 `authorize()` 中 `Gate::inspect('update')`） |
| 资源 / 授权 | `app/Http/Resources/TagResource.php`、`app/Policies/TagPolicy.php` |
| 控制器 | `app/Http/Controllers/Api/Content/TagController.php`（单表 CRUD，不含 Action） |
| 测试 | `tests/Feature/TagTest.php`：列表、CRUD、校验、无权限 403 |
| 登记（修改） | `config/permissions.php`：`content.tag` 节点（view/create/update/delete）；`routes/api.php`：`apiResource('tags')` + `can:` 中间件 + `use`；`lang/*/operations.php`：`tags.store/update/destroy` 摘要 |
| 前端 | `web/src/pages/content/tags/{index.tsx,service.ts,data.d.ts}`（列表 + 新建 / 编辑弹窗 + 删除，见第 6 节）、`web/src/locales/{zh-CN,en-US}/pages/content-tags.ts`；修改 `config/routes.ts`、`src/locales/{zh-CN,en-US}/menu.ts`、`src/locales/{zh-CN,en-US}.ts`、`src/access.ts` |

命名推导：类 `Tag` → 复数 `Tags`、表 `tags`、路由 / URL `tags`、路由参数 `tag`、权限码 `content.tag`、前端路径 `/content/tags`、
文案前缀 `pages.content.tags`、菜单键 `menu.content.tags`。

生成后请补充：`docs/architecture.md` 中的契约、Policy 中的业务规则（如"被引用时不可删"）、Seeder、更合适的中文名称
（权限点 `name`、`operations.php`、前端文案默认用英文 Headline），并完成第 6.3 节的前端收尾步骤。

## 4. 模板（stub）

模板位于 `server/stubs/edge/`，与 Laravel `stub:publish` 理念一致：**直接修改即可定制生成结果**。
查找顺序由 `config('edge.generator.stub_paths')` 决定（默认只有 `stubs/edge`），把自己的目录放在前面即可只覆盖个别模板。

### 4.1 模板清单

| 模板 | 用途 |
|---|---|
| `migration` `model` `factory` `index_request` `store_request` `update_request` `resource` `policy` `controller` `test` | 后端文件 |
| `policy.data_scope` `controller.data_scope` | `--data-scope` 时的变体（`<名称>.<变体>.stub` 优先于 `<名称>.stub`） |
| `routes` | 插入 `routes/api.php` 的路由块 |
| `permission` / `permission_group` | 插入 `config/permissions.php` 的权限节点 / 新模块分组 |
| `operations.<locale>` / `operations` | 插入 `lang/<locale>/operations.php` 的摘要（`operations.zh_CN.stub`，其余语言用 `operations.stub`） |
| `fragments/<片段>[.<字段类型>]` | 后端按字段重复的片段，见 4.3 |
| `web/index.tsx` `web/service.ts` `web/data.d.ts` | 前端页面文件（见第 6 节） |
| `web/locale.<locale>` / `web/locale` | 页面文案文件（`web/locale.zh-CN.stub`，其余语言用 `web/locale.stub`） |
| `web/route` / `web/route_group` | 插入 `config/routes.ts` 的路由 / 新模块分组 |
| `web/menu_module` `web/menu_item`（可加 `.<locale>`） | 插入 `menu.ts` 的模块与菜单文案 |
| `web/locale_import` `web/locale_spread` | 在 `src/locales/<locale>.ts` 中注册页面文案 |
| `web/permission_codes` | 插入 `src/access.ts` 的 `PermissionCode` 联合类型 |
| `web/fragments/<片段>[.<字段类型>]` | 前端按字段重复的片段，见 6.2 |

### 4.2 模块变量

所有模板与片段可用（`{{ 名称 }}`，括号内空格可省略）。下表以 `Tag --module=content` 为例：

| 变量 | 示例 | 变量 | 示例 |
|---|---|---|---|
| `class` | `Tag` | `class_plural` | `Tags` |
| `variable` | `tag` | `variable_plural` | `tags` |
| `title` | `Tag` | `title_plural` | `Tags` |
| `module` | `content` | `module_studly` | `Content` |
| `module_title` | `Content` | `table` | `tags` |
| `route` | `tags` | `route_parameter` | `tag` |
| `permission` | `content.tag` | `controller_namespace` | `App\Http\Controllers\Api\Content` |
| `request_namespace` | `App\Http\Requests\Content` | `fillable` | `'name', 'color', 'sort', 'status'` |
| `sortable` | `'id', 'sort', 'created_at'` | `traits` | `HasFactory`（含 `HasDataScope` / `SoftDeletes`） |
| `data_scope` / `soft_deletes` | `true` 或空 | `uses` | 本模板中各片段所需的 `use` 导入 |
| `migration.extras` | 数据权限列 | `migration.soft_deletes` | `$table->softDeletes();` |
| `casts.extras` `factory.extras` `resource.extras` | 数据权限字段 | `locale` | 仅语言相关模板：`zh-CN` / `en-US` |
| `web.path` | `/content/tags` | `web.page_dir` | `content/tags` |
| `web.component` | `TagList` | `web.locale_prefix` | `pages.content.tags` |
| `web.menu_module` | `menu.content` | `web.menu_key` | `menu.content.tags` |
| `web.locale_file` | `content-tags` | `web.locale_module` | `contentTags` |
| `permission_node` | 仅 `permission_group`：已渲染的权限节点 | `route_node` | 仅 `web/route_group`：已渲染的路由 |

### 4.3 字段片段（按字段重复渲染）

模板中写 `{{ fields.<片段> }}`，生成器对**每个字段**渲染片段模板并以换行连接：

- 后端片段：`fragments/<片段>.<字段类型>.stub` → 不存在时 `fragments/<片段>.stub` → 都不存在则该字段跳过；
- 前端片段：`{{ fields.web.<片段> }}` 对应 `web/fragments/<片段>[.<字段类型>].stub`；
- 片段可使用全部模块变量与下方 `field.*` 变量；**片段引用的任一 `field.*` 值为 null 时，该字段在此片段中跳过**
  （例如 `string` 没有 cast，`cast` 片段自然不会输出它）；
- 想为某种类型单独定制，新建 `<片段>.<类型>.stub` 即可（如 `web/fragments/column.status.stub`）。

现有片段：后端 `migration` `cast` `rules` `filter_rule` `filter_scope` `factory` `resource` `relation.foreign` `test_payload` `test_setup.dict`；
前端 `web.type` `web.locale` `web.column` `web.column_state` `web.hook` `web.form_hook` `web.initial_value` `web.form_item`（清单见 6.2）。

| `field.*` 变量 | 说明（以 `name:string:required\|max:50` 为例） |
|---|---|
| `field.name` / `field.camel` / `field.label` | `name` / `name` / `Name` |
| `field.type` / `field.argument` | `string` / foreign 的表名或 dict 的编码 |
| `field.dict` / `field.related` / `field.relation` | 字典编码 / 关联模型类 / 关联方法名（非对应类型为 null） |
| `field.migration` | `$table->string('name', 50)` |
| `field.cast` | cast 表达式，无则 null |
| `field.rules` | `['required', 'string', 'max:50']` |
| `field.filter_rule` / `field.filter_scope` | 列表过滤的校验规则 / 模型 `filter()` 中的一条语句，不可过滤的类型为 null |
| `field.factory` / `field.resource` / `field.test_value` | Factory 表达式 / Resource 取值表达式 / 测试提交值 |
| `field.ts_type` / `field.value_type` / `field.web_search` | `string` / `text` / 不可过滤时为 `, search: false` |

### 4.4 渲染规则

- 独占一行的占位符渲染为空时，整行删除；随后清理紧跟 `{` `[` `(` 之后、紧挨 `}` `]` `)` 之前的空行与连续空行。
- PHP 文件最终会对顶层 `use` 去重、去掉同命名空间的导入并按字母排序（与 Pint 一致），因此模板里的导入顺序无需关心。
- 未识别的占位符原样保留（TSX 中的 `{{ ... }}` 对象字面量不受影响）。

## 5. 锚点与幂等

修改已有文件时，生成器**只在锚点注释前插入**，并先检查文件中是否已有该模块的"标识"文本（如 `'code' => 'content.tag'`、
`Route::apiResource('tags',`、`'tags.store'`、`path: '/content/tags'`），已有则跳过，因此重复执行不会产生重复内容。
插入内容按锚点行的缩进对齐。找不到锚点时**不会猜测位置**，而是打印需要手动粘贴的代码片段。

| 文件 | 锚点 | 插入内容 |
|---|---|---|
| `server/config/permissions.php` | `// edge:permissions:<模块>`（模块 children 末尾） | 权限节点 |
| 同上 | `// edge:permissions`（顶层数组末尾） | 模块不存在时：新模块分组（自带 `// edge:permissions:<模块>`） |
| `server/routes/api.php` | `// edge:routes`（`auth:sanctum` 分组内） | `apiResource` 路由块；同时在 `use` 区按序添加控制器导入 |
| `server/lang/<locale>/operations.php` | `// edge:operations`（数组末尾） | 三条操作日志摘要 |
| `web/config/routes.ts` | `// edge:routes:<模块>`（该模块分组的 `routes` 数组末尾） | 路由对象 |
| 同上 | `// edge:routes`（顶层路由数组中） | 模块分组不存在时：新分组（自带 `// edge:routes:<模块>`） |
| `web/src/locales/<locale>/menu.ts` | `// edge:menu`（对象末尾） | `'menu.<模块>'`（不存在时）与 `'menu.<模块>.<路由>'` |
| `web/src/locales/<locale>.ts` | `// edge:locale-imports`（import 区末尾） | `import <web.locale_module> from './<locale>/pages/<web.locale_file>';` |
| 同上 | `// edge:locale-spread`（默认导出对象末尾） | `...<web.locale_module>,` |
| `web/src/access.ts` | `// edge:permission-codes`（`PermissionCode` 联合类型中、末尾 `\| never` 之前） | `\| '<权限码>.view'` 等四行 |

前后端锚点均已就位（`web/` 中的锚点注释请勿删除；Biome 格式化后位置不变）。前端写法：

```ts
// config/routes.ts —— 每个模块分组的 routes 末尾 + 顶层数组中（/account/settings 之前）
      // edge:routes:content
    ],
  },
  // edge:routes

// src/access.ts —— 末尾的 `| never` 不影响类型，只为让锚点留在联合类型内部
// （直接写 `// edge:permission-codes` + `;` 时 Biome 会把 `;` 移到锚点之前）
export type PermissionCode =
  | 'content.article.delete'
  // edge:permission-codes
  | never;

// src/locales/<lang>.ts —— import 区末尾 + 默认导出对象末尾
// edge:locale-imports
  // edge:locale-spread

// src/locales/<lang>/menu.ts —— 对象末尾
  // edge:menu
```

`web/` 目录不存在（或 `--no-web`）时跳过前端部分；目录位置由 `EDGE_GENERATOR_WEB_PATH`（默认 `../web`）指定，
语言目录由 `config('edge.generator.web_locales')`（默认 `zh-CN`、`en-US`）指定。

## 6. 前端模板

生成的页面与手写页面遵循同一套规范（[frontend-guide.md](frontend-guide.md)）：`PageContainer` + `ProTable`（搜索、分页、
排序、列设置，`tableRequest`）+ 新建 / 编辑 `ModalForm`（`formRef`、`destroyOnHidden`、编辑时 `request` 拉详情、422 字段回填）
+ `DeleteAction` + `access.can()` 按钮级权限 + 完整的 `service.ts` / `data.d.ts` + 双语文案。
因为生成器只渲染 `index.tsx` / `service.ts` / `data.d.ts` 三个页面文件，表单组件 `<Class>Form` 与列表放在同一个 `index.tsx` 中
（手写页面放在 `components/`，二者等价，需要时可自行拆出）。

### 6.1 页面模板与占位符

| 模板 | 用到的片段 / 变量 |
|---|---|
| `web/index.tsx` | `web.form_hook` `web.initial_value` `web.form_item`（表单组件）；`web.hook` `web.column` `web.column_state`（列表）；`web.component` `web.locale_prefix` `permission` `class` `class_plural` |
| `web/service.ts` | `query<Classes>` / `get<Class>` / `create<Class>` / `update<Class>` / `delete<Class>`，路径 `/api/{{ route }}` |
| `web/data.d.ts` | `web.type`（实体与 `FormValues` 共用） |
| `web/locale.zh-CN` / `web/locale` | `web.locale`；另含 `<prefix>.create` / `<prefix>.edit` 与 `permission.<权限码>`（角色授权树中的菜单名，操作名走通用的 `permission.action.*`） |
| `web/permission_codes` | 四个权限码（`\| '<权限码>.view'` …） |
| `web/route` / `web/route_group` / `web/menu_*` / `web/locale_import` / `web/locale_spread` | 见第 5 节 |

页面模板一次性导入所有字段类型可能用到的控件（`ProFormText`、`ProFormDigit`、`useDict`、`DictTag`……），
未用到的导入由生成后的 `biome check --write --unsafe` 删除（见 6.3）。

### 6.2 字段片段（`web/fragments/`）

| 片段 | 作用 | 有模板的类型 |
|---|---|---|
| `type` | `data.d.ts` 字段：`{{ field.name }}: {{ field.ts_type }};` | 全部（通用模板） |
| `locale` | 字段文案 `'<prefix>.<camel>': '<Label>'` | 全部（通用模板） |
| `column` | ProTable 列 | 全部类型各一个；`column.stub` 为通用回退 |
| `column_state` | 默认隐藏的列（`columnsState`） | `text` `json` |
| `hook` | 列表组件中的 hook | `status`（`useStatusEnum`）、`boolean`（`useBooleanEnum`）、`dict`（`useDict`） |
| `form_hook` | 表单组件中的 hook | `status` `dict` |
| `initial_value` | 新建时的默认值 | `status`（`'active'`）、`boolean`（`false`） |
| `form_item` | 表单控件 | 全部类型各一个 |

各类型生成的列与控件：

| type | 列 | 搜索 | 排序 | 表单控件 |
|---|---|---|---|---|
| `string` | 文本 | 模糊 | — | `ProFormText` |
| `text` | `ellipsis`，默认隐藏 | — | — | `ProFormTextArea` |
| `integer` | `digit` | 精确 | ✓ | `ProFormDigit`（precision 0） |
| `decimal` | `digit` | — | ✓ | `ProFormDigit`（precision 2） |
| `boolean` | `useBooleanEnum()` 是 / 否 | 下拉，`booleanTransform` 转 1 / 0 | — | `ProFormSwitch`（默认 false） |
| `date` | `date` | 按日 | ✓ | `ProFormDatePicker` |
| `datetime` | `dateTime` | — | ✓ | `ProFormDateTimePicker` |
| `json` | `JSON.stringify` + `ellipsis`，默认隐藏 | — | — | `ProFormTextArea` + `jsonRule`，`convertValue={stringifyJson}`、`transform` 中 `parseJson` |
| `status` | `useStatusEnum()` | 下拉 | — | `ProFormRadio.Group`（默认 active） |
| `foreign:<表>` | `select` + 占位 `request: async () => []`（TODO 注释） | 下拉 | — | `ProFormSelect` + 占位 `request`（TODO 注释） |
| `dict:<编码>` | `valueEnum: xxxDict.valueEnum` + `DictTag` 渲染 | 下拉 | — | `ProFormSelect options={xxxDict.options}` |

排序只对后端 `sortable`（`id`、integer / decimal / date / datetime 字段、`created_at`）开启，与生成的 `IndexRequest` 一致；
`id`、`created_at` 列与操作列由页面模板固定输出。

**定制**：直接修改 `server/stubs/edge/web/**`；只想改某种类型时新建 / 修改 `web/fragments/<片段>.<类型>.stub`；
新增片段时在页面模板写 `{{ fields.web.<片段名> }}` 即可（没有对应类型模板、也没有通用模板的字段自动跳过）。
也可以把自己的模板目录放到 `config('edge.generator.stub_paths')` 前面，只覆盖个别文件。

### 6.3 生成后的前端收尾

```bash
cd web
npx biome check --write --unsafe src/pages/<模块>/<路由> src/locales src/access.ts config/routes.ts  # 去掉未用到的导入 + 格式化 + 导入排序
npm run lint && npx antd lint ./src && npm test && npm run build
```

还需要手工调整：

1. **外键下拉**：`foreign` 字段的列与表单中有 `TODO`，把占位的 `async () => []` 换成选项接口，
   如 `getArticleCategoryOptions().then(toSelectOptions)`（没有现成接口时先在契约与后端补 `/api/options/*`）。
2. **前端校验**：生成的控件不带 `rules`（模板拿不到字段是否必填 / 长度，见 6.4），提交后由后端 422 回填到字段；
   需要即时校验时自行加 `rules={[{ required: true }, { max: 50 }]}`。表单已用 `clearFieldErrors` 在修改时清除回填的错误。
3. **文案**：`zh-CN` 的字段名默认为英文 Headline，改成中文；菜单名在 `menu.ts`，角色授权树里的菜单名在页面文案的 `permission.<权限码>`。
   新模块分组的 `permission.<模块>` 未生成，缺失时回退后端 `name`。
4. **列宽 / 默认隐藏列**：字段多时按 [frontend-guide.md](frontend-guide.md) 的列表规范调整 `width`、`columnsState`，保证 1440 宽下常用列完整显示。
5. **数据权限**：`--data-scope` 时后端资源额外返回 `department_id`、`created_by`，`data.d.ts` 不会自动包含，需要展示时自行补充。
6. **日期时间**：`ProFormDateTimePicker` 提交的是不带时区的本地时间（`YYYY-MM-DD HH:mm:ss`），按服务器 `APP_TIMEZONE` 解释；
   浏览器与服务器不在同一时区时会有偏差（后端目前也会丢弃带时区偏移的输入，见遗留问题）。
7. 契约：在 `docs/architecture.md` 补接口、权限码、排序白名单。

### 6.4 已知的引擎限制

前端模板目前受以下限制（改引擎前用上面的收尾步骤绕过）：

- **没有 TS 导入去重**：PHP 文件会整理 `use`，TSX 不会；同一类型出现多次时无法按字段渲染不重复的 import，
  因此模板导入全集，再由 `biome --unsafe` 清理。若引擎提供按片段收集并去重的 `{{ web.imports }}`，可以去掉这一步。
- **字段变量缺少前端需要的信息**：没有 `field.required` / `field.nullable` / `field.max` 这类原始值（只有 PHP 形式的 `field.rules`），
  模板无法生成与后端一致的 `rules`、`required` 星号与 `maxLength`。
- **没有条件块 / 前端模块变量**：`data_scope`、`soft_deletes` 只能整行删除，无法让 `data.d.ts` 按 `--data-scope` 追加
  `department_id` / `created_by`（后端有 `resource.extras` 等专用变量，前端没有对应的 `web.*.extras`）。
- **页面文件固定为三个**：无法生成 `components/<Class>Form.tsx`，表单只能与列表同文件。
- **`foreign` 只有表名**：没有对应的 options 接口信息，只能生成占位 `request`。

## 7. 测试

生成器自身的测试见 `server/tests/Feature/MakeModuleCommandTest.php`：在临时目录中复制含锚点的真实文件，覆盖全部字段类型、
`--data-scope` / `--soft-deletes`、重复执行不重复、`--force`、`--dry-run`、`--no-web` 与前端目录缺失、锚点缺失时的提示、
自定义 stub 目录优先，以及各类错误输入。
