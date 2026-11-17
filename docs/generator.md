# 代码生成器 `edge:make-module`

`edge:make-module` 是一个**编排器**：后端文件全部由 Laravel 官方 `make:*` 命令（以及两个同样继承 `GeneratorCommand` 的项目命令）按
`server/stubs/*.stub` 生成——命名空间、路径、`--force`、stub 解析都走官方逻辑；编排器只做官方命令覆盖不到的部分：
按字段补全骨架（迁移列、`#[Fillable]`、`casts()`、Factory、校验规则、Resource 字段、列表过滤 / 排序白名单、Feature 测试），
幂等地登记菜单权限（`config/permissions.php`，带 `path` / `icon`，sync 后**自动出现在动态菜单里**）、路由、操作日志摘要，生成前端页面，
最后用 Pint / Biome 格式化。生成物只是起点：多步写入、副作用、记录级规则等仍按 [backend-guide.md](backend-guide.md) 手工补充。

```bash
cd server
php artisan edge:make-module Tag --module=content --with-options \
  --fields="name:string:required|max:50,color:string:nullable,sort:integer,status:status"

php artisan permission:sync && php artisan migrate     # 完成：菜单自动出现在侧边栏（非超级管理员需在角色管理中授权）
```

只敲 `php artisan edge:make-module` 时用 Laravel Prompts 依次询问模型名、模块、字段与可选能力（数据权限 / 软删除 / 选项接口 / 前端页面）；
给了名称但缺 `--module` / `--fields` 时只补问缺的项；`--no-interaction` 时缺项直接报错。

生成物无需手工清理即可通过两端全部检查：后端 `composer lint && composer analyse && composer test`（含生成的 `TagTest`），
前端 `npm run lint && npx antd lint ./src && npm run build`。

## 1. 参数

| 参数 / 选项 | 说明 |
|---|---|
| `name` | 模型名，单数 StudlyCase（`Tag`、`ArticleTag`）。表名 / 路由 / 权限码由它推导，见第 3 节 |
| `--module=` | **必填**（交互时询问）。所属模块（小写字母、数字、`-`），决定权限码前缀、控制器 / 请求命名空间、菜单目录（`/<模块>`），如 `content` |
| `--fields=` | **必填**（交互时询问）。字段定义，见第 2 节 |
| `--data-scope` | 接入数据权限：模型 `use HasDataScope`，迁移加 `department_id` / `created_by`，控制器 `visibleTo()` + `#[Authorize('view', …)]`，Policy `isVisibleTo()` |
| `--soft-deletes` | 迁移 `softDeletes()`，模型 `use SoftDeletes`，生成的测试断言软删除 |
| `--with-options` | 同时生成选项接口 `GET /api/options/<路由>`（`make:option-controller`，返回 `[{id, name}]`；有 `status` 字段时只返回启用项，有 `sort` 字段时按其排序）。要求声明 `name` 字段 |
| `--no-web` | 不生成前端代码 |
| `--force` | 覆盖已存在的文件：传给支持 `--force` 的 make 命令；`make:factory` 没有该选项，先删除旧文件；迁移沿用原文件名（避免已执行的迁移被当作新迁移）。登记类修改始终幂等 |
| `--dry-run` | 只打印将调用的 make 命令、将要创建 / 修改的文件与需手动处理的事项，不调用命令、不写入 |

## 2. 字段 DSL

`name:type[:参数][:rules]`，多个字段用逗号分隔。

- `rules` 用 `|` 分隔，原样透传给 Laravel 校验，可以包含 `:` 和 `,`（如 `in:low,high`）；只有"逗号后紧跟 `字段名:`"才会被当作新字段。
- 未声明 `required` / `nullable` / `sometimes` / `present` / `filled` 时默认 `required`；声明了 `nullable` 时迁移列可空。
- `string` 的 `max:N`（≤255）同时决定迁移列长度；未声明时追加 `max:255`。
- 必填 / 可空 / `max` 同时下发给前端：表单控件生成 `rules={[{ required: true }]}`、`fieldProps={{ maxLength: N }}`（数字为 `max={N}`），见 7.2。
- `id`、`created_at`、`updated_at`、`deleted_at`、`department_id`、`created_by` 由生成器维护，不能声明。

| type | 迁移 | cast | 校验（类型部分） | 列表过滤 | TS 类型 / valueType |
|---|---|---|---|---|---|
| `string` | `string(N)` | — | `string` `max:255` | 模糊 | `string` / `text` |
| `text` | `text` | — | `string` | — | `string` / `textarea` |
| `integer` | `integer` | `integer` | `integer` | 精确 | `number` / `digit` |
| `decimal` | `decimal(10,2)` | `decimal:2` | `numeric` | — | `string` / `digit` |
| `boolean` | `boolean` 默认 false | `boolean` | `boolean` | 精确 | `boolean` / `switch` |
| `date` | `date` | `date` | `date` | 按日 | `string` / `date` |
| `datetime` | `dateTime` | `datetime` | `date`（接受带偏移的 ISO 8601，见 7.2） | — | `string` / `dateTime` |
| `json` | `json` | `array` | `array` | — | `Record<string, unknown>` / `jsonCode` |
| `status` | `string(20)` 默认 active | `Status::class` | `Rule::enum(Status::class)` | 精确 | `API.Status` / `select` |
| `foreign:<表>` | `foreignId()->constrained('<表>')` | `integer` | `integer` `Rule::exists('<表>', 'id')` | 精确 | `number` / `digit` |
| `dict:<编码>` | `string(64)` 带索引 | — | `string` `new InDict('<编码>')` | 精确 | `string` / `text` |

`foreign:<表>` 会按表名推导关联模型（`article_categories` → `ArticleCategory`），生成 `belongsTo` 关系与 Factory 关联；模型需已存在。
前端下拉的选项来源见 7.4。字段文案取去掉 `_id` 的名称（`tag_id` → `Tag`）。
`dict:<编码>` 生成的测试会自动准备该字典与一个值为 `sample` 的字典项。

## 3. 生成过程与内容

以 `Tag --module=content` 为例，按顺序执行（`callSilently`，均带 `--no-interaction`；目标文件已存在且未 `--force` 时跳过该命令并提示）：

| 命令 | 模板 | 生成 | 编排器补全 |
|---|---|---|---|
| `make:model Tag` | `stubs/model.stub` | `app/Models/Tag.php` | `#[Fillable([...])]`、`casts()`、`HasDataScope` / `SoftDeletes`、`belongsTo` 关系、`#[Scope] filter()`（`stubs/edge/model_filter.stub`） |
| `make:factory TagFactory --model=Tag` | 官方 `factory.stub` | `database/factories/TagFactory.php` | `definition()` 字段 |
| `make:migration create_tags_table --create=tags` | 官方 `migration.create.stub` | `database/migrations/<时间>_create_tags_table.php` | `$table->id();` 之后的列、`$table->timestamps();` 之后的 `softDeletes()` |
| `make:policy TagPolicy --model=Tag` | `stubs/policy.stub` | `app/Policies/TagPolicy.php` | `--data-scope` 时 `view` / `update` / `delete` 改为 `isVisibleTo($actor)` |
| `make:request Content/StoreTagRequest`、`Content/UpdateTagRequest` | `stubs/request.stub` | `app/Http/Requests/Content/…` | `rules()` |
| `make:index-request Content/TagIndexRequest` | `stubs/index-request.stub` | 继承 `ListRequest` | `$sortable`、`filterRules()` |
| `make:resource TagResource` | `stubs/resource.stub` | `app/Http/Resources/TagResource.php` | `@mixin Tag`、`toArray()` 字段 |
| `make:controller Api/Content/TagController --model=Tag --type=module` | `stubs/controller.module.stub`（`--data-scope` 时 `controller.module.data-scope.stub`） | `app/Http/Controllers/Api/Content/TagController.php` | 替换模板中官方命令不认识的 `{{ permission }}` `{{ routeParameter }}` `{{ requestNamespace }}` |
| `make:option-controller TagOptionController --model=Tag`（`--with-options`） | `stubs/controller.option.stub` | `app/Http/Controllers/Api/Options/TagOptionController.php` | 启用项过滤与排序 |
| `make:test TagTest --pest` | — | `tests/Feature/TagTest.php` | 内容为模块接口测试 `stubs/edge/test.stub`：列表、CRUD（datetime 断言时区换算、软删除断言）、校验、无权限 403、选项接口 |

然后在已有文件的锚点处登记（第 6 节）：

| 文件 | 内容 |
|---|---|
| `config/permissions.php` | `content.tag` 菜单节点（`type: menu`、`path: /content/tags`、`icon: TableOutlined`、view/create/update/delete 操作；模块分组不存在时新建 `type: directory` 的目录） |
| `routes/api.php` | `Route::apiResources([...])` 中的 `'tags' => TagController::class,`（`--with-options` 时另在 `options` 分组登记 `Route::get('tags', TagOptionController::class)`）；导入由 Pint 合并进已有的 `use …\Content\{…};` |
| `lang/*/operations.php` | `tags.store/update/destroy` 摘要 |
| 前端 | `web/src/pages/content/tags/{index.tsx,service.ts,data.d.ts,components/TagForm.tsx}`、`web/src/locales/{zh-CN,en-US}/pages/content-tags.ts`；修改 `config/routes.ts`、`src/locales/{zh-CN,en-US}.ts`、`src/access.ts` |

最后对本次创建 / 修改的 PHP 文件执行 Pint 两遍（`--no-config --preset=laravel` 拆开分组导入，再按 `pint.json` 重新导入、合并、排序——补全代码中写的
完整类名如 `\App\Enums\Status::class` 因此变成 `use` 导入），对 TS 文件执行 `web/node_modules/.bin/biome check --write`（未安装依赖时只提示）。

生成的控制器（功能权限与记录级授权都以 Attribute 声明，见 [permissions.md](permissions.md)）：

```php
class TagController extends Controller
{
    #[Authorize('content.tag.view')]
    public function index(TagIndexRequest $request): AnonymousResourceCollection { /* filter → applySort → paginate */ }

    #[Authorize('content.tag.update')]
    #[Authorize('update', 'tag')]
    public function update(UpdateTagRequest $request, Tag $tag): TagResource { /* ... */ }
    // ...
}
```

命名推导：类 `Tag` → 复数 `Tags`、表 `tags`、路由 / URL `tags`、路由参数 `tag`、权限码 `content.tag`、前端路径 `/content/tags`、
文案前缀 `pages.content.tags`、菜单名称 `permission.content.tag`（页面文案中生成，与权限节点 `name` 同为 `Tags`）。

生成后请补充：`docs/architecture.md` 中的契约（含 `--with-options` 的选项接口）、Policy 中的业务规则（如"被引用时不可删"——
被外键 `restrictOnDelete()` 引用的记录直接删除会触发数据库约束错误）、Seeder、更合适的中文名称（见 7.3）。

## 4. 单独使用 make 命令

所有 make 命令都可以单独使用，产物遵循本项目规范（Attribute 配置的模型、无 `authorize()` 的 Form Request、只含记录级规则的 Policy、
带返回类型的资源控制器）。生成后执行 `composer format`：Pint 的 `group_import` 需要把官方命令逐行写出的同命名空间导入合并，其余检查（Larastan、架构测试）直接通过。

```bash
php artisan make:model Foo -mf --policy
php artisan make:controller Api/Demo/FooController --api --model=Foo --requests
php artisan make:resource FooResource
php artisan make:index-request Demo/FooIndexRequest          # 项目命令：继承 ListRequest 的列表请求
php artisan make:option-controller FooOptionController --model=Foo   # 项目命令：GET /api/options/<路由>
php artisan make:test FooTest --pest
composer format
```

`make:index-request` / `make:option-controller`（`app/Console/Commands/*MakeCommand.php`）继承 `Illuminate\Console\GeneratorCommand`，
行为与官方一致：名称可带子命名空间、已存在时报错、`--force` 覆盖、缺名称时询问、模板取自 `stubs/`。

## 5. 模板

### 5.1 官方命令的模板（`server/stubs/`，`stub:publish` 机制）

| 模板 | 定制点 |
|---|---|
| `model.stub` | `#[Fillable([])]`、`use HasFactory, StoresDatesInAppTimezone;`（所有模型必须使用，架构测试强制）、`casts()` 方法 |
| `request.stub` | 不生成 `authorize()`（授权在控制器动作上），只有 `rules()` |
| `resource.stub` | `toArray()` 返回显式字段数组（`//` 占位，避免 `parent::toArray()`） |
| `policy.stub` | 只有记录级方法 `update` / `delete`（返回 `Response`），参数名 `$actor`；不生成 `viewAny` / `create` 等功能权限方法 |
| `controller.model.api.stub` | 带返回类型的单表 CRUD（`make:controller --api --model=… --requests`） |
| `controller.module.stub` / `controller.module.data-scope.stub` | 生成器专用（`make:controller --type=module…`）：`ListRequest` 列表、`#[Authorize]` 权限 |
| `index-request.stub` / `controller.option.stub` | 项目命令 `make:index-request` / `make:option-controller` 的模板 |

未定制的官方模板（`factory`、`migration.create`、`pest` …）不放在仓库里，直接使用框架自带版本；需要时执行
`php artisan stub:publish` 后只保留要改的文件。**修改 `stubs/` 下的模板即可定制生成结果**，单独使用 make 命令与生成器都会生效。

编排器按约定位置补全骨架（`App\Console\Generator\SourceFile`）：`casts()` / `definition()` / `rules()` / `filterRules()` / `toArray()` 中的 `//` 占位行、
`#[Fillable([])]`、`use HasFactory, StoresDatesInAppTimezone;`、`$table->id();`、`$table->timestamps();`、`protected array $sortable = […];`、`return Response::allow();`。
定制模板时保留这些位置；被改掉时生成器**不猜测**，而是提示"模板中未找到 …，请手动补充"并打印要补的代码。

### 5.2 模块模板（`server/stubs/edge/`）

| 模板 | 用途 |
|---|---|
| `fragments/<片段>[.<字段类型>]` | 按字段重复的后端代码：`migration` `cast` `rules` `filter_rule` `filter_scope` `factory` `resource` `relation.foreign` `test_payload` `test_setup.dict` `test_assertion.datetime` |
| `model_filter` | 追加到模型的 `#[Scope] filter()`（内含 `{{ fields.filter_scope }}`） |
| `test` | 模块接口测试（写入 `make:test` 创建的文件） |
| `resource_route` / `option_route` | 插入 `routes/api.php` 的 `apiResources` 条目 / `options` 分组路由 |
| `permission` / `permission_group` | 插入 `config/permissions.php` 的菜单节点 / 新模块目录 |
| `operations.<locale>` / `operations` | 插入 `lang/<locale>/operations.php` 的摘要（`operations.zh_CN.stub`，其余语言用 `operations.stub`） |
| `web/**` | 前端页面、文案、路由与权限码登记，见第 7 节 |

片段与登记片段中的类写完整类名（`\App\Enums\Status::class`、`new \App\Rules\InDict('…')`），导入由 Pint 整理。

### 5.3 变量与渲染规则（`App\Console\Generator\StubRenderer`）

- `{{ 名称 }}`：模块变量（下表），键可含 `.`；独占一行且值为空时整行删除；未识别的占位符原样保留（TSX 中的 `{{ ... }}` 对象字面量不受影响）。
- `{{ fields.<片段> }}` / `{{ fields.web.<片段> }}`：对每个字段渲染 `fragments/<片段>.<字段类型>.stub`（不存在时 `fragments/<片段>.stub`，都不存在则跳过该字段）；
  片段中可用全部模块变量与 `field.*` 变量，**引用的任一 `field.*` 值为 null 时该字段在此片段中跳过**（如 `string` 没有 cast）。
- `{{#if 名称}} … {{else}} … {{/if}}` / `{{#unless 名称}} … {{/unless}}`：条件块，可嵌套，由内向外求值，值为 null / 空串 / 不存在时为假；
  标签独占一行时连同该行删除；条件先于占位符执行，因此只出现在假分支里的 `field.*` 不会导致字段被跳过。
- 渲染后清理可选内容为空留下的多余空行。

| 变量 | 示例 | 变量 | 示例 |
|---|---|---|---|
| `class` / `class_plural` | `Tag` / `Tags` | `variable` / `variable_plural` | `tag` / `tags` |
| `title` / `title_plural` | `Tag` / `Tags` | `module` / `module_studly` / `module_title` | `content` / `Content` / `Content` |
| `table` / `route` / `route_parameter` | `tags` / `tags` / `tag` | `permission` | `content.tag` |
| `controller_namespace` / `request_namespace` | `App\Http\Controllers\Api\Content` / `App\Http\Requests\Content` | `fillable` / `sortable` | `'name', 'color', 'sort', 'status'` / `'id', 'sort', 'created_at'` |
| `data_scope` / `soft_deletes` / `with_options` | `true` 或空（条件块用） | `status_field` / `sort_field` | 第一个 `status` 字段名 / 有 `sort:integer` 时为 `sort` |
| `web.path` / `web.page_dir` | `/content/tags` / `content/tags` | `web.component` / `web.form_component` | `TagList` / `TagForm` |
| `web.locale_prefix` / `web.locale_file` / `web.locale_module` | `pages.content.tags` / `content-tags` / `contentTags` | `locale` | 仅语言相关模板：`zh-CN` / `en-US` |
| `permission_node` / `route_node` | 仅 `permission_group` / `web/route_group`：已渲染的子节点 | | |

| `field.*` 变量 | 说明（以 `name:string:required\|max:50` 为例） |
|---|---|
| `field.name` / `field.camel` / `field.label` | `name` / `name` / `Name`（foreign 去掉 `_id`：`tag_id` → `Tag`） |
| `field.type` / `field.argument` | `string` / foreign 的表名或 dict 的编码 |
| `field.required` / `field.nullable` | `true` / 空 |
| `field.max` | `50`：生效的 `max:N`（`string` 未声明时为 `255`），没有为 null |
| `field.dict` / `field.related` / `field.related_class` / `field.relation` | 字典编码 / 关联模型类名 / 完整类名 / 关联方法名（非对应类型为 null） |
| `field.related_route` / `field.options_route` | foreign：关联表的选项路由段 / 可用的选项接口路由段（见 7.4，不可用为 null） |
| `field.migration` / `field.cast` / `field.rules` | `$table->string('name', 50)` / cast 表达式（无则 null）/ `['required', 'string', 'max:50']` |
| `field.filter_rule` / `field.filter_scope` | 列表过滤的校验规则 / 模型 `filter()` 中的一条语句，不可过滤的类型为 null |
| `field.factory` / `field.resource` / `field.test_value` | Factory 表达式 / Resource 取值表达式 / 测试提交值 |
| `field.ts_type` / `field.value_type` / `field.web_search` | `string` / `text` / 不可过滤时为 `, search: false` |

### 5.4 TS 导入（`App\Console\Generator\TypeScriptImports`）

前端模板与片段直接写普通的 `import … from '…';`（顶格、单行，支持 `type`、默认导入与命名导入），写在片段里也可以：

```text
import { ProFormText } from '@ant-design/pro-components';
      <ProFormText name="{{ field.name }}" … />
```

渲染后收集全文中**实际输出**的 import 行（被跳过的片段、条件块假分支中的不计入），按来源合并、去重、排序后放到文件开头，
顺序与 Biome organizeImports 一致：npm 包 → `@/` 别名 → 相对路径，同一来源 `import type` 在前，超过 80 字符的命名导入换行。

## 6. 锚点与幂等

修改已有文件时，生成器**只在锚点注释前插入**，并先检查文件中是否已有该模块的"标识"文本（如 `'code' => 'content.tag'`、
`'tags' => `、`TagOptionController::class`、`'tags.store'`、`path: '/content/tags'`），已有则跳过，因此重复执行不会产生重复内容。
插入内容按锚点行的缩进对齐。找不到锚点时**不会猜测位置**，而是打印需要手动粘贴的代码片段。

| 文件 | 锚点 | 插入内容 |
|---|---|---|
| `server/config/permissions.php` | `// edge:permissions:<模块>`（模块 children 末尾） | 权限节点 |
| 同上 | `// edge:permissions`（顶层数组末尾） | 模块不存在时：新模块分组（自带 `// edge:permissions:<模块>`） |
| `server/routes/api.php` | `// edge:resources`（业务模块的 `Route::apiResources([...])` 数组末尾） | `'<路由>' => \App\…\<Class>Controller::class,`（导入由 Pint 合并） |
| 同上 | `// edge:options`（`options` 分组末尾） | `--with-options`：`Route::get('<路由>', …OptionController::class)->name('<路由>')` |
| `server/lang/<locale>/operations.php` | `// edge:operations`（数组末尾） | 三条操作日志摘要 |
| `web/config/routes.ts` | `// edge:routes:<模块>` / `// edge:routes` | 路由对象 / 新模块分组 |
| `web/src/locales/<locale>.ts` | `// edge:locale-imports` / `// edge:locale-spread` | 导入与展开页面文案 |
| `web/src/access.ts` | `// edge:permission-codes`（`PermissionCode` 联合类型末尾 `\| never` 之前） | 四个权限码 |

`web/` 目录不存在（或 `--no-web`）时跳过前端部分；目录位置由 `EDGE_GENERATOR_WEB_PATH`（默认 `../web`）指定，
语言目录由 `config('edge.generator.web_locales')`（默认 `zh-CN`、`en-US`）指定。

## 7. 前端模板

生成的页面与手写页面遵循同一套规范（[frontend-guide.md](frontend-guide.md)）：`index.tsx` 为 `PageContainer` + `ProTable`（搜索、分页、
排序、列设置，`tableRequest`）+ `DeleteAction` + `access.can()` 按钮级权限；`components/<Class>Form.tsx` 为新建 / 编辑 `ModalForm`
（`formRef`、`destroyOnHidden`、编辑时 `request` 拉详情、422 字段回填、与后端规则一致的前端校验）；外加完整的 `service.ts` / `data.d.ts` 与双语文案。

### 7.1 页面模板与占位符

| 模板 | 用到的片段 / 变量 |
|---|---|
| `web/index.tsx` | `web.hook` `web.column` `web.column_state`；`web.component` `web.form_component` `web.locale_prefix` `permission` `class` `class_plural`；`{{#if data_scope}}` 部门 / 创建人列 |
| `web/form.tsx` | `web.form_hook` `web.initial_value` `web.form_item`；`web.form_component` `web.locale_prefix` `class` |
| `web/service.ts` | `query<Classes>` / `get<Class>` / `create<Class>` / `update<Class>` / `delete<Class>`，路径 `/api/{{ route }}` |
| `web/data.d.ts` | `web.type`（实体与 `FormValues` 共用）；`{{#if data_scope}}` 时实体追加 `department_id` / `created_by` |
| `web/locale.zh-CN` / `web/locale` | `web.locale`；另含 `<prefix>.create` / `<prefix>.edit`、`--data-scope` 时的 `<prefix>.department` / `<prefix>.creator` 与 `permission.<权限码>`（侧边菜单 / 菜单管理 / 角色授权树中的菜单名，操作名走通用的 `permission.action.*`） |
| `web/permission_codes` | 四个权限码（`\| '<权限码>.view'` …） |
| `web/route` / `web/route_group` / `web/locale_import` / `web/locale_spread` | 见第 6 节（静态路由只提供页面与路由级权限，菜单的名称 / 图标 / 顺序来自服务端；路由带 `locale: 'permission.<权限码>'`，页面标题 / 面包屑复用页面文案中的菜单名称） |

页面模板与各片段直接写普通的 `import … from '…';`（顶格、单行）声明自己用到的导入，生成后由 `TypeScriptImports` 收集、合并、去重、排到文件开头（见 5.4），因此结果只包含实际用到的导入。

### 7.2 字段片段（`web/fragments/`）

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
| `foreign:<表>` | `select` + `request: () => fetchSelectOptions('<路由>')`，按名称显示 | 下拉 | — | `ProFormSelect` + 同一 `request`（无选项接口时为 TODO 占位，见 7.4） |
| `dict:<编码>` | `valueEnum: xxxDict.valueEnum` + `DictTag` 渲染 | 下拉 | — | `ProFormSelect options={xxxDict.options}` |

排序只对后端 `sortable`（`id`、integer / decimal / date / datetime 字段、`created_at`）开启，与生成的 `IndexRequest` 一致；
`id`、`created_at` 列与操作列由页面模板固定输出。`--data-scope` 时另有"部门""创建人"两列（`fetchSelectOptions('departments')` /
`fetchSelectOptions('users')` 按名称显示，默认隐藏，可在列设置中打开）。

**定制**：直接修改 `server/stubs/edge/web/**`；只想改某种类型时新建 / 修改 `web/fragments/<片段>.<类型>.stub`；
新增片段时在页面模板写 `{{ fields.web.<片段名> }}` 即可（没有对应类型模板、也没有通用模板的字段自动跳过）。

### 7.3 生成后

```bash
cd server && php artisan permission:sync && php artisan migrate     # 完成：两端检查均可直接通过，新菜单自动出现在侧边栏
```

按需继续完善（都不是通过检查的前提）：

1. **契约**：在 `docs/architecture.md` 补接口、权限码、排序白名单（`--with-options` 时还有 `/api/options/<路由>`）。
2. **文案**：`zh-CN` 的字段名默认为英文 Headline，改成中文；菜单名称在页面文案的 `permission.<权限码>`，**同时**把
   `config/permissions.php` 中节点的 `name` 改成同样的中文（前端只在后端 `name` 等于 zh-CN 文案时才按语言翻译，否则视为管理员改过名、直接显示 `name`）。
   已 sync 过的节点名称不会被再次 sync 覆盖，可在菜单管理界面修改。新模块目录的 `permission.<模块>` 未生成，缺失时显示后端 `name`；图标也可以在菜单管理中更换。
3. **业务规则**：Policy（如"被引用时不可删"）、Seeder、多步写入 / 副作用改用 Action。
4. **列宽 / 默认隐藏列**：字段多时按 [frontend-guide.md](frontend-guide.md) 的列表规范调整 `width`、`columnsState`，保证 1440 宽下常用列完整显示。
5. 冒烟用例：在 `web/e2e/smoke.spec.ts` 中加上新页面。

### 7.4 外键下拉的选项接口

前端统一调用 `src/services/options.ts` 的 `fetchSelectOptions('<路由>')`（`GET /api/options/<路由>` → `{label, value}[]`，
树形选项会展开）。生成器按以下顺序决定 `foreign:<表>` 字段的 `field.options_route`：

1. 本次 `--with-options` 且关联的就是本模块的表（自关联）→ 本模块的选项接口；
2. `routes/api.php` 中已有 `Route::get('<表名 kebab>', XxxOptionController::class)`（如 `article-categories`、`users`、`departments`、
   `roles`、`positions`，或之前用 `--with-options` 生成的模块）→ 直接使用；
3. 都没有 → 生成器打印提示，前端列与控件生成 `request: async () => []` 占位与 TODO 注释。为关联模块执行
   `edge:make-module <关联模型> … --with-options --force`（或手写 Options 控制器并在契约中登记）后，把占位改为 `fetchSelectOptions('<路由>')`，
   或对本模块 `--force` 重新生成前端文件。

### 7.5 已知限制

- 选项接口只支持 `[{id, name}]`（要求 `name` 字段）；需要按其他字段显示、按条件过滤或远程搜索时请手写。
- 列表不生成 `datetime` 与数据权限字段的搜索条件；`json` / `text` 只用于展示与编辑。
- 表单只有一列布局，字段多时可自行改为 `Row` / `Col` 或 `StepsForm`。

## 8. 代码结构与测试

| 位置 | 职责 |
|---|---|
| `app/Console/Commands/MakeModuleCommand.php` | 编排器：解析输入（含 Prompts 交互）、调用 make 命令、补全、登记、格式化、报告 |
| `app/Console/Commands/{IndexRequest,OptionController}MakeCommand.php` | 项目 make 命令（`GeneratorCommand`） |
| `app/Console/Generator/{Field,FieldParser}.php` | 字段 DSL 与各字段的代码表达式 |
| `app/Console/Generator/ModuleBlueprint.php` | 命名推导与模板变量 |
| `app/Console/Generator/SourceFile.php` | 在约定位置补全 make 命令生成的骨架 |
| `app/Console/Generator/{StubRenderer,TypeScriptImports,TextInserter}.php` | 模块模板渲染 / TS 导入整理 / 锚点插入 |

`server/tests/Feature/MakeModuleCommandTest.php`：每个测试把应用的 base / app / database / lang / config 路径指向临时目录（`setBasePath()`），
复制 `stubs/`、`pint.json` 与含锚点的真实文件，仓库本身不会被修改。覆盖：单独执行官方 make 命令的产物（规范、语法、`composer format` 后通过 Pint）、
项目 make 命令（`--force`、已存在报错、stub 覆盖）、全部字段类型 + `--data-scope` / `--soft-deletes` 的编排结果（含 Pint 合并导入、无残留占位符）、
动态菜单联动、`--with-options` 与外键选项接口检测、重复执行不重复、`--force`（迁移文件名不变）、`--dry-run`、`--no-web` 与前端目录缺失、
锚点缺失与模板补全位置缺失的提示、自定义 stub 与字段片段生效、Prompts 交互提问、各类错误输入、条件块与 TS 导入整理、字段 DSL 中的逗号。
