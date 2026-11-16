# 代码生成器 `edge:make-module`

一条命令生成一个符合本项目规范的单表 CRUD 业务模块（后端全套 + 前端占位页面），并**幂等地**登记权限点、路由、操作日志摘要与前端路由 / 菜单 / 文案。
生成物只是起点：多步写入、副作用、记录级规则等仍按 [backend-guide.md](backend-guide.md) 手工补充。

```bash
cd server
php artisan edge:make-module Tag --module=content \
  --fields="name:string:required|max:50,color:string:nullable,sort:integer,status:status"

php artisan migrate
php artisan permission:sync
php artisan test --filter=TagTest
composer lint && composer analyse
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
| 前端 | `web/src/pages/content/tags/{index.tsx,service.ts,data.d.ts}`、`web/src/locales/{zh-CN,en-US}/pages/content-tags.ts`；修改 `config/routes.ts`、`src/locales/{zh-CN,en-US}/menu.ts`、`src/locales/{zh-CN,en-US}.ts`、`src/access.ts` |

命名推导：类 `Tag` → 复数 `Tags`、表 `tags`、路由 / URL `tags`、路由参数 `tag`、权限码 `content.tag`、前端路径 `/content/tags`、
文案前缀 `pages.content.tags`、菜单键 `menu.content.tags`。

生成后请补充：`docs/architecture.md` 中的契约、Policy 中的业务规则（如"被引用时不可删"）、Seeder、更合适的中文名称
（权限点 `name`、`operations.php`、前端文案默认用英文 Headline）。

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
| `web/index.tsx` `web/service.ts` `web/data.d.ts` | 前端页面文件 |
| `web/locale.<locale>` / `web/locale` | 页面文案文件（`web/locale.zh-CN.stub`，其余语言用 `web/locale.stub`） |
| `web/route` / `web/route_group` | 插入 `config/routes.ts` 的路由 / 新模块分组 |
| `web/menu_module` `web/menu_item`（可加 `.<locale>`） | 插入 `menu.ts` 的模块与菜单文案 |
| `web/locale_import` `web/locale_spread` | 在 `src/locales/<locale>.ts` 中注册页面文案 |
| `web/permission_codes` | 插入 `src/access.ts` 的 `PermissionCode` 联合类型 |
| `web/fragments/<片段>[.<字段类型>]` | 前端按字段重复的片段 |

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
前端 `web.column` `web.type` `web.locale`。

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
| `web/src/access.ts` | `// edge:permission-codes`（`PermissionCode` 联合类型中、`;` 之前） | `\| '<权限码>.view'` 等四行 |

后端锚点已就位。**前端锚点需要前端同事在 `web/` 中添加**（后端任务不修改 `web/`），参考写法：

```ts
// config/routes.ts —— 每个模块分组的 routes 末尾 + 顶层数组中（如 /account/settings 之前）
      // edge:routes:content
    ],
  },
  // edge:routes

// src/access.ts
  | 'content.article.delete'
  // edge:permission-codes
  ;
```

`web/` 目录不存在（或 `--no-web`）时跳过前端部分；目录位置由 `EDGE_GENERATOR_WEB_PATH`（默认 `../web`）指定，
语言目录由 `config('edge.generator.web_locales')`（默认 `zh-CN`、`en-US`）指定。

## 6. 前端 stub 交接说明

当前 `stubs/edge/web/*.stub` 是**最小可用的占位实现**：只读 ProTable 列表（列来自 `web.column` 片段、`tableRequest(query<Class>s)`、
`headerTitle` 使用 `<prefix>.title` 文案），以及完整的 `service.ts`（查询 / 新增 / 编辑 / 删除）和 `data.d.ts`（实体与表单类型）。
已在复制的 `web/` 中验证：加锚点 → 生成 → `tsc --noEmit` 与 `biome check` 通过。

前端同事可直接修改这些 stub（新增 / 编辑表单、删除按钮与 `access.can()`、状态列 `useStatusEnum()`、字典列等）。
按字段重复的内容请放在 `web/fragments/` 中，并可按字段类型拆分（如 `column.status.stub`、`column.dict.stub`、`form_item.<类型>.stub`），
新增片段只需在页面模板中写 `{{ fields.web.<片段名> }}`，无需改动生成器代码。

## 7. 测试

生成器自身的测试见 `server/tests/Feature/MakeModuleCommandTest.php`：在临时目录中复制含锚点的真实文件，覆盖全部字段类型、
`--data-scope` / `--soft-deletes`、重复执行不重复、`--force`、`--dry-run`、`--no-web` 与前端目录缺失、锚点缺失时的提示、
自定义 stub 目录优先，以及各类错误输入。
