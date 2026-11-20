# Content

业务模块，目录 `app/Modules/Content`。结构与规则见设计文档 §4.3、§5；接口契约见设计文档 [§6.11](../../../../docs/backend-design.md#611-业务模块示范content文章)。

**业务对接示范，可整块删除**（设计文档 §4.2）。它证明业务模块**不改 Core 的任何文件**，只通过扩展点（§5.5）就能用上 Core 的全部能力；
也是 `core:make-crud`（§10.4）生成结果的样板：生成器沿用这里的分层与写法（Query 类、Policy 的三段式、锁行复核的 Action、软删除整套、导出、审计），
只是不带发布流程、封面、字典分类与富文本这些业务部分。改动这里的写法时，同步修改 `app/Core/Generator/stubs` 并跑 `composer test:generator-e2e`。

## 职责

- 文章（`Models/Article`，多态别名 `content.article`）：标题、摘要、正文（富文本）、分类（字典）、状态（草稿 / 已发布）、发布时间、封面（图片附件）、创建人；
- 写操作（`Actions/`）：新增、修改、删除（软删除）、恢复、彻底删除、发布、取消发布；每个写操作在同一事务中写审计日志（`LogArticleActivity`）；
- 授权（`Policies/ArticlePolicy`）：权限码 + 业务保护（只有草稿能发布等）+ 数据范围（按 `created_by`）；
- 列表查询（`Queries/ArticleList`）与导出（`Exporters/ArticleExporter`）共用同一份白名单与数据范围。

不负责：接口的路由、参数校验与输出格式（在入口 `app/Http/Admin/Content`）；前端页面。

## 对外提供

| 类 | 用途 |
|---|---|
| `Models/Article` | 文章：`COVER_COLLECTION`（封面的附件用途）、`CATEGORY_DICT`（分类的字典类型）、`cover()`、`creator()`、`isDraft()`、`isPublished()` |
| `Enums/ArticleStatus` | 状态 `draft` / `published`（`HasLabel`、`HasColor`，`/options/enums` 的 `content.article_status`） |
| `Actions/CreateArticle`、`UpdateArticle` | 新增（草稿）、修改；封面用 `SyncAttachments` 绑定 |
| `Actions/PublishArticle`、`UnpublishArticle` | 发布、取消发布（锁行复核状态，并发 422） |
| `Actions/DeleteArticle`、`RestoreArticle`、`ForceDeleteArticle` | 软删除、恢复、彻底删除（软删除整套，§10.1） |
| `Actions/LogArticleActivity` | 审计日志（日志名 `content`），由上面的 Action 在同一事务中调用 |
| `Policies/ArticlePolicy` | 标准方法 + `publish`、`unpublish`、`viewTrashed` |
| `Queries/ArticleList` | 列表查询（query-builder 白名单 + `visibleTo` + `trashed` 的授权） |
| `Exporters/ArticleExporter` | 列表的导出（`content.article`，权限码 `content.article.export`） |

- 受信任的入口直接读取本模块的模型，写入调用 `Actions`；接口放在各受信任入口的同名目录：`app/Http/Admin/Content`（路由在 `app/Http/Admin/routes.php` 的 `content` 分组）。
- 受限的入口只通过 `Contracts` 与 `Data` 访问（目前没有）。
- 事件：没有（其他模块与入口只能通过事件、`Contracts` / `Data` 使用本模块）。

## 依赖 Core 的能力

全部通过扩展点或 Core 公开的类，在 `ContentServiceProvider::boot()` 中登记；**没有修改 `app/Core` 的任何文件**。

| Core 的能力 | 本模块怎么用 |
|---|---|
| 菜单与权限节点（`MenuRegistry`，§8.2） | `resources/menus.php`：目录 `content`、菜单 `content.article` 与按钮 `create`、`update`、`delete`、`publish`、`restore`、`force-delete`、`export` |
| 权限判定（`Gate::before`、Policy，§8.1、§8.3） | `ArticlePolicy`（按 `\Models\` → `\Policies\` 自动发现），控制器 `#[Authorize]` |
| 数据权限（`HasDataScope`，§8.4） | `Article` 使用它，归属列为默认的 `created_by`；列表、详情、写操作、导出都经过它 |
| 字典（`DictRegistry`、`InDict`，§6.7） | `resources/dicts.php` 声明 `content.article_category` 与初始项；`category` 用 `InDict` 校验：只接受启用项，修改时文章当前的分类即使已停用也可原样提交（`allowing()`，保持原值不拦） |
| 枚举选项（`EnumRegistry`，§6.7） | 登记 `content.article_status` |
| 富文本净化（`SanitizedHtml`，§9） | `content` 列的 cast（默认档位 `rich`） |
| 附件（`HasAttachments`、`OwnedAttachment`、`SyncAttachments`，§6.8） | 封面：`cover` 用途的图片附件；归属校验、绑定、彻底删除时连同文件删除；访问权委托给 `ArticlePolicy::view` |
| 异步导出（`Exporter`、`ExporterRegistry`、`CreateExport`，§6.10） | 登记 `content.article`；入口 `POST /content/articles/export` |
| 审计日志（activitylog，§5.5、§6.6） | 日志名 `content`；`request_id` 由 Monitor 的 `Activity` 自动写入。审计与业务写入在**同一个事务**中，失败一起回滚；这与操作日志不同（操作日志在响应后写入、失败只 `report()`），审计记录的是数据变更本身，必须与数据一致 |
| 多态别名（§4.5） | `content.article`（附件的 `attachable_type`、日志的 `subject_type`） |
| 语言包（§4.5） | `resources/lang`，命名空间 `content::`（枚举显示名、提示文案、导出表头） |

## 删除本模块

本模块是示范，项目不需要文章时整块删除。其余模块与 Core 的测试不依赖它（`tests/Arch` 保证 Core 不依赖 `App\Modules`；Core 的菜单树测试按 name 定位节点、只比较 Core 的目录）。

1. 删除目录与文件：
   - `app/Modules/Content`（本目录）；
   - `app/Http/Admin/Content`；
   - `database/migrations/*_create_articles_table.php`、`database/factories/Modules/Content`；
   - `tests/Modules/Content`、`tests/Http/Admin/Content`。
2. 删除登记：
   - `bootstrap/providers.php` 中的 `ContentServiceProvider`（及其 `use`）；
   - `app/Http/Admin/routes.php` 中的 `content` 路由分组（及 `use App\Http\Admin\Content\Controllers\{...}`）。
3. 删除文档：设计文档 §6.11 与 §8.6 的“文章”；§10.1 的软删除规范保留（它是通用规范），把其中的样板引用改为生成器。
4. 数据库：已部署的环境用一个新迁移 `Schema::dropIfExists('articles')`（不要删除已执行过的迁移后指望它回滚）；
   绑定在文章上的封面附件（`attachable_type = content.article`）随表一起失去宿主，用同一个迁移删除这些附件记录与文件，或保留由管理员处理。
   `activity_log` 中 `log_name = content` 的审计日志按保留天数自动清理，也可在迁移中删除。
5. 同步声明：执行 `php artisan core:sync`。
   - 菜单与权限节点 `content`、`content.article.*`：内置节点**转为自定义节点**（保留角色的授权，不删除），在菜单管理中删除即可（先删按钮、再删菜单与目录：有子节点的不能删）；
   - 字典 `content.article_category`：内置类型与字典项**转为自定义**，在字典管理中删除即可；
   - 不想手工清理时，可在第 4 步的迁移中按 name / code 删除这些节点与字典（角色授权随节点级联删除）。
6. 执行 `composer ci`：其余测试应全部通过。

## 生成命令

```bash
php artisan core:make-crud <模型> --module=Content        # 另一个模型的完整增删改查（--soft-deletes、--export、--audit、--no-data-scope）
php artisan core:make-model <名称> --module=Content       # 模型、迁移、工厂、Policy、语言包，登记多态别名
php artisan core:make-action <名称> --module=Content      # 写操作
php artisan core:make-query <名称> --module=Content       # 复杂查询、统计
php artisan core:make-enum <名称> --module=Content        # 枚举（实现 HasLabel）
php artisan core:make-contract <名称> --module=Content    # 向受限入口开放的契约
php artisan core:make-controller <名称> --module=Content --model=<模型>   # 后台接口
```
