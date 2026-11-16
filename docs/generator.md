# 代码生成器 `edge:make-module`

一条命令生成一个符合本项目规范的单表 CRUD 业务模块（后端全套 + 前端列表 / 表单页面），并**幂等地**登记权限点、路由、操作日志摘要与前端路由 / 菜单 / 文案。
生成物只是起点：多步写入、副作用、记录级规则等仍按 [backend-guide.md](backend-guide.md) 手工补充。

```bash
cd server
php artisan edge:make-module Tag --module=content --with-options \
  --fields="name:string:required|max:50,color:string:nullable,sort:integer,status:status"

php artisan permission:sync && php artisan migrate     # 完成
```

生成物无需手工清理即可通过两端全部检查：后端 `composer lint && composer analyse && composer test`（含生成的 `TagTest`），
前端 `npm run lint && npx antd lint ./src && npm run build`（`web/node_modules` 存在时生成器会自动用 Biome 格式化生成 / 修改的前端文件）。
之后按需补充契约、Policy 业务规则、Seeder 与中文文案（见第 3 节末尾）。

## 1. 参数

| 参数 / 选项 | 说明 |
|---|---|
| `name` | 模型名，单数 StudlyCase（`Tag`、`ArticleTag`）。表名 / 路由 / 权限码由它推导，见第 3 节 |
| `--module=` | **必填**。所属模块（小写字母、数字、`-`），决定权限码前缀、控制器 / 请求命名空间、前端菜单分组，如 `content` |
| `--fields=` | **必填**。字段定义，见第 2 节 |
| `--data-scope` | 接入数据权限：模型 `use HasDataScope`，迁移加 `department_id` / `created_by`，控制器 `visibleTo()`，Policy `isVisibleTo()` |
| `--soft-deletes` | 迁移 `softDeletes()`，模型 `use SoftDeletes`，生成的测试断言软删除 |
| `--with-options` | 同时生成选项接口 `GET /api/options/<路由>`（`Options/<Class>OptionController`，返回 `[{id, name}]`；有 `status` 字段时只返回启用项，有 `sort` 字段时按其排序）。要求声明 `name` 字段 |
| `--no-web` | 不生成前端代码 |
| `--force` | 覆盖已存在的文件（登记类修改始终幂等，不会重复） |
| `--dry-run` | 只打印将要创建 / 修改的文件与需手动处理的事项，不写入 |

## 2. 字段 DSL

`name:type[:参数][:rules]`，多个字段用逗号分隔。

- `rules` 用 `|` 分隔，原样透传给 Laravel 校验，可以包含 `:` 和 `,`（如 `in:low,high`）；只有"逗号后紧跟 `字段名:`"才会被当作新字段。
- 未声明 `required` / `nullable` / `sometimes` / `present` / `filled` 时默认 `required`；声明了 `nullable` 时迁移列可空。
- `string` 的 `max:N`（≤255）同时决定迁移列长度；未声明时追加 `max:255`。
- 必填 / 可空 / `max` 同时下发给前端：表单控件生成 `rules={[{ required: true }]}`、`fieldProps={{ maxLength: N }}`（数字为 `max={N}`），见 6.2。
- `id`、`created_at`、`updated_at`、`deleted_at`、`department_id`、`created_by` 由生成器维护，不能声明。

| type | 迁移 | cast | 校验（类型部分） | 列表过滤 | TS 类型 / valueType |
|---|---|---|---|---|---|
| `string` | `string(N)` | — | `string` `max:255` | 模糊 | `string` / `text` |
| `text` | `text` | — | `string` | — | `string` / `textarea` |
| `integer` | `integer` | `integer` | `integer` | 精确 | `number` / `digit` |
| `decimal` | `decimal(10,2)` | `decimal:2` | `numeric` | — | `string` / `digit` |
| `boolean` | `boolean` 默认 false | `boolean` | `boolean` | 精确 | `boolean` / `switch` |
| `date` | `date` | `date` | `date` | 按日 | `string` / `date` |
| `datetime` | `dateTime` | `datetime` | `date`（接受带偏移的 ISO 8601，见 6.2） | — | `string` / `dateTime` |
| `json` | `json` | `array` | `array` | — | `Record<string, unknown>` / `jsonCode` |
| `status` | `string(20)` 默认 active | `Status::class` | `Rule::enum(Status::class)` | 精确 | `API.Status` / `select` |
| `foreign:<表>` | `foreignId()->constrained('<表>')` | `integer` | `integer` `Rule::exists('<表>', 'id')` | 精确 | `number` / `digit` |
| `dict:<编码>` | `string(64)` 带索引 | — | `string` `new InDict('<编码>')` | 精确 | `string` / `text` |

`foreign:<表>` 会按表名推导关联模型（`article_categories` → `ArticleCategory`），生成 `belongsTo` 关系与 Factory 关联；模型需已存在。
前端下拉的选项来源见 6.4。字段文案取去掉 `_id` 的名称（`tag_id` → `Tag`）。
`dict:<编码>` 生成的测试会自动准备该字典与一个值为 `sample` 的字典项。

## 3. 生成内容

以 `Tag --module=content` 为例：

| 类别 | 路径 / 内容 |
|---|---|
| 迁移 | `database/migrations/<时间>_create_tags_table.php`（已存在同名迁移时复用其文件名） |
| 模型 / 工厂 | `app/Models/Tag.php`（`#[Fillable]`、`casts()`、关系、`#[Scope] filter()`）、`database/factories/TagFactory.php` |
| 请求 | `app/Http/Requests/Content/{TagIndexRequest,StoreTagRequest,UpdateTagRequest}.php`（Index 继承 `ListRequest`；Update 在 `authorize()` 中 `Gate::inspect('update')`） |
| 资源 / 授权 | `app/Http/Resources/TagResource.php`、`app/Policies/TagPolicy.php` |
| 控制器 | `app/Http/Controllers/Api/Content/TagController.php`（单表 CRUD，不含 Action）；`--with-options` 时另有 `app/Http/Controllers/Api/Options/TagOptionController.php` |
| 测试 | `tests/Feature/TagTest.php`：列表、CRUD（datetime 字段断言时区换算、软删除断言 `assertSoftDeleted`）、校验、无权限 403；`--with-options` 时含选项接口 |
| 登记（修改） | `config/permissions.php`：`content.tag` 节点（view/create/update/delete）；`routes/api.php`：`apiResource('tags')` + `can:` 中间件 + `use`（`--with-options` 时另在 `options` 分组登记 `Route::get('tags', TagOptionController::class)`）；`lang/*/operations.php`：`tags.store/update/destroy` 摘要 |
| 前端 | `web/src/pages/content/tags/{index.tsx,service.ts,data.d.ts,components/TagForm.tsx}`（列表 + 删除、新建 / 编辑弹窗，见第 6 节）、`web/src/locales/{zh-CN,en-US}/pages/content-tags.ts`；修改 `config/routes.ts`、`src/locales/{zh-CN,en-US}/menu.ts`、`src/locales/{zh-CN,en-US}.ts`、`src/access.ts` |

命名推导：类 `Tag` → 复数 `Tags`、表 `tags`、路由 / URL `tags`、路由参数 `tag`、权限码 `content.tag`、前端路径 `/content/tags`、
文案前缀 `pages.content.tags`、菜单键 `menu.content.tags`。

生成的模型与手写模型一样 `use StoresDatesInAppTimezone`（日期时间输入换算到应用时区，见 [backend-guide.md](backend-guide.md) 3 节"日期时间"）。

生成后请补充：`docs/architecture.md` 中的契约（含 `--with-options` 的选项接口）、Policy 中的业务规则（如"被引用时不可删"——
被外键 `restrictOnDelete()` 引用的记录直接删除会触发数据库约束错误）、Seeder、更合适的中文名称
（权限点 `name`、`operations.php`、前端文案默认用英文 Headline），见 6.3。

## 4. 模板（stub）

模板位于 `server/stubs/edge/`，与 Laravel `stub:publish` 理念一致：**直接修改即可定制生成结果**。
查找顺序由 `config('edge.generator.stub_paths')` 决定（默认只有 `stubs/edge`），把自己的目录放在前面即可只覆盖个别模板。

### 4.1 模板清单

| 模板 | 用途 |
|---|---|
| `migration` `model` `factory` `index_request` `store_request` `update_request` `resource` `policy` `controller` `test` | 后端文件 |
| `policy.data_scope` `controller.data_scope` | `--data-scope` 时的变体（`<名称>.<变体>.stub` 优先于 `<名称>.stub`） |
| `routes` | 插入 `routes/api.php` 的路由块 |
| `option_controller` / `option_route` | `--with-options` 的选项控制器 / 插入 `routes/api.php` `options` 分组的路由 |
| `permission` / `permission_group` | 插入 `config/permissions.php` 的权限节点 / 新模块分组 |
| `operations.<locale>` / `operations` | 插入 `lang/<locale>/operations.php` 的摘要（`operations.zh_CN.stub`，其余语言用 `operations.stub`） |
| `fragments/<片段>[.<字段类型>]` | 后端按字段重复的片段，见 4.3 |
| `web/index.tsx` `web/form.tsx` `web/service.ts` `web/data.d.ts` | 前端页面文件（`form.tsx` → `components/<Class>Form.tsx`，见第 6 节） |
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
| `data_scope` / `soft_deletes` / `with_options` | `true` 或空（用于条件块，见 4.4） | `uses` | 本模板中各片段所需的 `use` 导入 |
| `status_field` | 第一个 `status` 字段名，没有为空 | `sort_field` | 有 `sort:integer` 字段时为 `sort`，否则为空 |
| `migration.extras` | 数据权限列（兼容旧模板；内置模板已改用条件块） | `migration.soft_deletes` | `$table->softDeletes();`（同上） |
| `casts.extras` `factory.extras` `resource.extras` | 数据权限字段（同上） | `locale` | 仅语言相关模板：`zh-CN` / `en-US` |
| `web.path` | `/content/tags` | `web.page_dir` | `content/tags` |
| `web.component` | `TagList` | `web.form_component` | `TagForm` |
| `web.locale_prefix` | `pages.content.tags` | `imports` | 仅前端模板：汇总后的 TS 导入，见 4.5 |
| `web.menu_module` | `menu.content` | `web.menu_key` | `menu.content.tags` |
| `web.locale_file` | `content-tags` | `web.locale_module` | `contentTags` |
| `permission_node` | 仅 `permission_group`：已渲染的权限节点 | `route_node` | 仅 `web/route_group`：已渲染的路由 |

### 4.3 字段片段（按字段重复渲染）

模板中写 `{{ fields.<片段> }}`，生成器对**每个字段**渲染片段模板并以换行连接：

- 后端片段：`fragments/<片段>.<字段类型>.stub` → 不存在时 `fragments/<片段>.stub` → 都不存在则该字段跳过；
- 前端片段：`{{ fields.web.<片段> }}` 对应 `web/fragments/<片段>[.<字段类型>].stub`；
- 片段可使用全部模块变量与下方 `field.*` 变量；**片段（条件块求值之后）引用的任一 `field.*` 值为 null 时，该字段在此片段中跳过**
  （例如 `string` 没有 cast，`cast` 片段自然不会输出它；只出现在 `{{#if field.max}}` 假分支里的 `{{ field.max }}` 不会导致跳过）；
- 想为某种类型单独定制，新建 `<片段>.<类型>.stub` 即可（如 `web/fragments/column.status.stub`）。

现有片段：后端 `migration` `cast` `rules` `filter_rule` `filter_scope` `factory` `resource` `relation.foreign` `test_payload` `test_setup.dict` `test_assertion.datetime`；
前端 `web.type` `web.locale` `web.column` `web.column_state` `web.hook` `web.form_hook` `web.initial_value` `web.form_item`（清单见 6.2）。

| `field.*` 变量 | 说明（以 `name:string:required\|max:50` 为例） |
|---|---|
| `field.name` / `field.camel` / `field.label` | `name` / `name` / `Name`（foreign 去掉 `_id`：`tag_id` → `Tag`） |
| `field.type` / `field.argument` | `string` / foreign 的表名或 dict 的编码 |
| `field.required` / `field.nullable` | `true` / 空：是否必填（声明了 `required` 或未声明存在性规则）/ 是否声明了 `nullable`；用于条件块 |
| `field.max` | `50`：生效的 `max:N`（`string` 未声明时为 `255`），没有为 null |
| `field.dict` / `field.related` / `field.relation` | 字典编码 / 关联模型类 / 关联方法名（非对应类型为 null） |
| `field.related_route` / `field.options_route` | foreign：关联表的选项路由段（`article_categories` → `article-categories`）/ 可用的选项接口路由段（见 6.4，不可用为 null） |
| `field.migration` | `$table->string('name', 50)` |
| `field.cast` | cast 表达式，无则 null |
| `field.rules` | `['required', 'string', 'max:50']` |
| `field.filter_rule` / `field.filter_scope` | 列表过滤的校验规则 / 模型 `filter()` 中的一条语句，不可过滤的类型为 null |
| `field.factory` / `field.resource` / `field.test_value` | Factory 表达式 / Resource 取值表达式 / 测试提交值 |
| `field.ts_type` / `field.value_type` / `field.web_search` | `string` / `text` / 不可过滤时为 `, search: false` |

### 4.4 条件块

```text
{{#if data_scope}} … {{/if}}
{{#if field.required}} … {{else}} … {{/if}}
{{#unless field.nullable}} … {{/unless}}
```

- 条件为模块变量或片段中的 `field.*`；值为 null、空串或不存在时为假（布尔变量用 `true` / 空表示）。可嵌套，由内向外求值。
- 标签独占一行时连同该行一起删除（不会留下空行），也可以写在行内：`// 长文本{{#if data_scope}} / 部门{{/if}}列默认隐藏`。
- 条件块先于占位符替换执行，适用于所有模板、片段与登记片段。

### 4.5 TS 导入声明

前端模板与片段用 `{{@import … from '…'}}`（独占一行，语法同 TS 的 `import`，支持 `type`、默认导入与命名导入）声明自己需要的导入：

```text
{{@import { ProFormText } from '@ant-design/pro-components'}}
      <ProFormText name="{{ field.name }}" … />
```

渲染后收集**实际输出**的声明（被跳过的片段、条件块假分支中的声明不计入），按来源合并、去重、排序，输出到 `{{ imports }}` 所在行
（没有该占位符时放在文件开头）。顺序与 Biome organizeImports 一致：npm 包 → `@/` 别名 → 相对路径，同一来源 `import type` 在前。
因此生成的 TSX 不含未使用的导入，直接通过 `npm run lint`。

### 4.6 渲染规则

- 独占一行的占位符渲染为空时，整行删除；随后清理紧跟 `{` `[` `(` 之后、紧挨 `}` `]` `)` 之前的空行与连续空行。
- PHP 文件最终会对顶层 `use` 去重、去掉同命名空间的导入并按字母排序（与 Pint 一致），因此模板里的导入顺序无需关心。
- 未识别的占位符原样保留（TSX 中的 `{{ ... }}` 对象字面量不受影响）。
- 写入文件后，若前端目录存在 `node_modules/.bin/biome`，生成器对本次生成 / 修改的 `.ts` / `.tsx` 执行 `biome check --write`
  （只做格式化与导入排序，不加 `--unsafe`）；未安装依赖或执行失败时只打印提示与可手动执行的命令，不影响生成结果。
  未经格式化的生成物同样能通过 `npm run lint`（Biome lint + tsc），只是个别长行 / 空对象的排版与 Biome 不同。

## 5. 锚点与幂等

修改已有文件时，生成器**只在锚点注释前插入**，并先检查文件中是否已有该模块的"标识"文本（如 `'code' => 'content.tag'`、
`Route::apiResource('tags',`、`'tags.store'`、`path: '/content/tags'`），已有则跳过，因此重复执行不会产生重复内容。
插入内容按锚点行的缩进对齐。找不到锚点时**不会猜测位置**，而是打印需要手动粘贴的代码片段。

| 文件 | 锚点 | 插入内容 |
|---|---|---|
| `server/config/permissions.php` | `// edge:permissions:<模块>`（模块 children 末尾） | 权限节点 |
| 同上 | `// edge:permissions`（顶层数组末尾） | 模块不存在时：新模块分组（自带 `// edge:permissions:<模块>`） |
| `server/routes/api.php` | `// edge:routes`（`auth:sanctum` 分组内） | `apiResource` 路由块；同时在 `use` 区按序添加控制器导入 |
| 同上 | `// edge:options`（`options` 分组末尾） | `--with-options`：`Route::get('<路由>', <Class>OptionController::class)` + 导入 |
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

生成的页面与手写页面遵循同一套规范（[frontend-guide.md](frontend-guide.md)）：`index.tsx` 为 `PageContainer` + `ProTable`（搜索、分页、
排序、列设置，`tableRequest`）+ `DeleteAction` + `access.can()` 按钮级权限；`components/<Class>Form.tsx` 为新建 / 编辑 `ModalForm`
（`formRef`、`destroyOnHidden`、编辑时 `request` 拉详情、422 字段回填、与后端规则一致的前端校验）；外加完整的 `service.ts` / `data.d.ts` 与双语文案。

### 6.1 页面模板与占位符

| 模板 | 用到的片段 / 变量 |
|---|---|
| `web/index.tsx` | `web.hook` `web.column` `web.column_state`；`web.component` `web.form_component` `web.locale_prefix` `permission` `class` `class_plural`；`{{#if data_scope}}` 部门 / 创建人列 |
| `web/form.tsx` | `web.form_hook` `web.initial_value` `web.form_item`；`web.form_component` `web.locale_prefix` `class` |
| `web/service.ts` | `query<Classes>` / `get<Class>` / `create<Class>` / `update<Class>` / `delete<Class>`，路径 `/api/{{ route }}` |
| `web/data.d.ts` | `web.type`（实体与 `FormValues` 共用）；`{{#if data_scope}}` 时实体追加 `department_id` / `created_by` |
| `web/locale.zh-CN` / `web/locale` | `web.locale`；另含 `<prefix>.create` / `<prefix>.edit`、`--data-scope` 时的 `<prefix>.department` / `<prefix>.creator` 与 `permission.<权限码>`（角色授权树中的菜单名，操作名走通用的 `permission.action.*`） |
| `web/permission_codes` | 四个权限码（`\| '<权限码>.view'` …） |
| `web/route` / `web/route_group` / `web/menu_*` / `web/locale_import` / `web/locale_spread` | 见第 5 节 |

页面模板与各片段用 `{{@import …}}` 声明自己用到的导入（4.5），生成结果只包含实际用到的导入。

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

表单控件按字段规则生成前端校验（`field.required` / `field.nullable` / `field.max`）：

- 必填（`required` 或未声明存在性规则）→ `rules={[{ required: true }]}`（`boolean` 除外，开关恒有值；`json` 与 `jsonRule` 合并）；
- `string` / `text` 的 `max` → `fieldProps={{ maxLength: N }}`（`string` 未声明时为 255）；`integer` / `decimal` 的 `max` → `max={N}`；
- 可空的 `date` / `dict` / `foreign` → `transform` 把清空后的 `undefined` 转成 `null`，保证"清空"会提交到后端；
- `datetime` → `transform` 中 `toOffsetDateTime()`：以浏览器本地时间显示与编辑，提交带时区偏移的 ISO 8601（契约第 2 节），与浏览器时区无关。

各类型生成的列与控件：

| type | 列 | 搜索 | 排序 | 表单控件 |
|---|---|---|---|---|
| `string` | 文本 | 模糊 | — | `ProFormText`（`maxLength`） |
| `text` | `ellipsis`，默认隐藏 | — | — | `ProFormTextArea`（声明 `max` 时 `maxLength` + `showCount`） |
| `integer` | `digit` | 精确 | ✓ | `ProFormDigit`（precision 0，`max`） |
| `decimal` | `digit` | — | ✓ | `ProFormDigit`（precision 2，`max`） |
| `boolean` | `useBooleanEnum()` 是 / 否 | 下拉，`booleanTransform` 转 1 / 0 | — | `ProFormSwitch`（默认 false） |
| `date` | `date` | 按日 | ✓ | `ProFormDatePicker` |
| `datetime` | `dateTime`（浏览器本地时间） | — | ✓ | `ProFormDateTimePicker` + `toOffsetDateTime` |
| `json` | `JSON.stringify` + `ellipsis`，默认隐藏 | — | — | `ProFormTextArea` + `jsonRule`，`convertValue={stringifyJson}`、`transform` 中 `parseJson` |
| `status` | `useStatusEnum()` | 下拉 | — | `ProFormRadio.Group`（默认 active） |
| `foreign:<表>` | `select` + `request: () => fetchSelectOptions('<路由>')`，按名称显示 | 下拉 | — | `ProFormSelect` + 同一 `request`（无选项接口时为 TODO 占位，见 6.4） |
| `dict:<编码>` | `valueEnum: xxxDict.valueEnum` + `DictTag` 渲染 | 下拉 | — | `ProFormSelect options={xxxDict.options}` |

排序只对后端 `sortable`（`id`、integer / decimal / date / datetime 字段、`created_at`）开启，与生成的 `IndexRequest` 一致；
`id`、`created_at` 列与操作列由页面模板固定输出。`--data-scope` 时另有"部门""创建人"两列（`fetchSelectOptions('departments')` /
`fetchSelectOptions('users')` 按名称显示，默认隐藏，可在列设置中打开）。

**定制**：直接修改 `server/stubs/edge/web/**`；只想改某种类型时新建 / 修改 `web/fragments/<片段>.<类型>.stub`；
新增片段时在页面模板写 `{{ fields.web.<片段名> }}` 即可（没有对应类型模板、也没有通用模板的字段自动跳过）。
也可以把自己的模板目录放到 `config('edge.generator.stub_paths')` 前面，只覆盖个别文件。

### 6.3 生成后

```bash
cd server && php artisan permission:sync && php artisan migrate     # 完成：两端检查均可直接通过
```

按需继续完善（都不是通过检查的前提）：

1. **契约**：在 `docs/architecture.md` 补接口、权限码、排序白名单（`--with-options` 时还有 `/api/options/<路由>`）。
2. **文案**：`zh-CN` 的字段名默认为英文 Headline，改成中文；菜单名在 `menu.ts`，角色授权树里的菜单名在页面文案的 `permission.<权限码>`。
   新模块分组的 `permission.<模块>` 未生成，缺失时回退后端 `name`。
3. **业务规则**：Policy（如"被引用时不可删"）、Seeder、多步写入 / 副作用改用 Action。
4. **列宽 / 默认隐藏列**：字段多时按 [frontend-guide.md](frontend-guide.md) 的列表规范调整 `width`、`columnsState`，保证 1440 宽下常用列完整显示。
5. 冒烟用例：在 `web/e2e/smoke.spec.ts` 中加上新页面。

### 6.4 外键下拉的选项接口

前端统一调用 `src/services/options.ts` 的 `fetchSelectOptions('<路由>')`（`GET /api/options/<路由>` → `{label, value}[]`，
树形选项会展开）。生成器按以下顺序决定 `foreign:<表>` 字段的 `field.options_route`：

1. 本次 `--with-options` 且关联的就是本模块的表（自关联）→ 本模块的选项接口；
2. `routes/api.php` 中已有 `Route::get('<表名 kebab>', XxxOptionController::class)`（如 `article-categories`、`users`、`departments`、
   `roles`、`positions`，或之前用 `--with-options` 生成的模块）→ 直接使用；
3. 都没有 → 生成器打印提示，前端列与控件生成 `request: async () => []` 占位与 TODO 注释。为关联模块执行
   `edge:make-module <关联模型> … --with-options --force`（或手写 Options 控制器并在契约中登记）后，把占位改为 `fetchSelectOptions('<路由>')`，
   或对本模块 `--force` 重新生成前端文件。

### 6.5 已知限制

- 选项接口只支持 `[{id, name}]`（要求 `name` 字段）；需要按其他字段显示、按条件过滤或远程搜索时请手写。
- 列表不生成 `datetime` 与数据权限字段的搜索条件；`json` / `text` 只用于展示与编辑。
- 表单只有一列布局，字段多时可自行改为 `Row` / `Col` 或 `StepsForm`。

## 7. 测试

生成器自身的测试见 `server/tests/Feature/MakeModuleCommandTest.php`：在临时目录中复制含锚点的真实文件，覆盖全部字段类型、
`--data-scope` / `--soft-deletes` / `--with-options`、前端导入去重与字段校验、外键选项接口检测、条件块与导入声明的渲染、
重复执行不重复、`--force`、`--dry-run`、`--no-web` 与前端目录缺失、锚点缺失时的提示、自定义 stub 目录优先，以及各类错误输入。
