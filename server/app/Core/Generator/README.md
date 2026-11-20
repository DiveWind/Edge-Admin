# Generator

内核模块，目录 `app/Core/Generator`：`core:make-*` 代码生成命令（设计文档 §10.4）。只在命令行下注册，不影响 HTTP 请求。

## 职责

按本项目的目录结构与规范生成文件（写法以业务模块示范 Content 为样板），并幂等地登记到共享文件；生成物不手改即通过 Pint、Rector、PHPStan 与 Pest（含架构测试）。
用法见 [`server/README.md`](../../../README.md#代码生成) 与 `php artisan list core`。

| 目录 | 内容 |
|---|---|
| `Commands/` | 各命令；基类 `CoreGeneratorCommand`（模板与条件块、名称、导入、退出码）、`ModuleGeneratorCommand`（`--module`、`--core`，按模型文件的写法生成）、`EntryGeneratorCommand`（`--app`）；`CrudMakeCommand` 编排完整增删改查 |
| `Editing/` | 修改共享文件：`bootstrap/providers.php`、`config/core.php`、`phpunit.xml`、`.env.example`、模块 Provider（多态别名、契约绑定、语言包、菜单、导出类）、模块的 `resources/menus.php`（`MenusFile`）、入口的 `routes.php`（`RoutesFile`）、模块 README（`ModuleReadmeFile`）；`PhpImports` 按 Pint 的格式输出导入 |
| `stubs/` | 默认模板。项目的 `stubs/core.<名称>.stub` 优先（`php artisan stub:publish` 或 `vendor:publish --tag=core-stubs` 发布） |

## 规则

- **模型是依据**：模型之外的命令读取模型文件的写法（`SoftDeletes`、`HasDataScope`、`creator()`、`@property string $name`，`ModuleGeneratorCommand::modelFeatures()`）决定生成什么，不另设选项；对手写的模型同样生成可通过检查的代码。
- **模板的条件块**：`{{#开关}}…{{/开关}}`（为真时保留）、`{{^开关}}…{{/开关}}`（为假时保留），独占一行的标记连同换行去掉；开关由各命令的 `sections()` 给出。不同名的块可嵌套，同名的不可以。
- **按名称识别标准用例**：`core:make-action` 的 `Create<模型>`、`Update<模型>`、`Delete<模型>`、`Restore<模型>`、`ForceDelete<模型>`（模型实现 `Auditable` 时写审计日志）；`core:make-controller --invokable` 的 `Export<模型复数>`（导出）、`ForceDelete<模型>` 与其他 `<动作><模型>`。
- **命名规则跟随设计文档 §4.4**：对外标识（多态别名、导出名）由 `ModuleGeneratorCommand::identifierFor()` 给出——业务模块 `<模块>.<模型>`，`--core` 时 `<模型>`；权限码总是 `<模块>.<模型>`（`permissionFor()`）；导出入口的单动作控制器与请求为 `Export<模型复数>`（`exportName()`）；审计测试为 `<模型>AuditTest`。改动规则时同步改这里与 `tests/Core/Generator`。
- **共享文件**：只认生成器自己生成的格式（与 Content、Core 的现有文件一致），找不到锚点时抛出 `UnexpectedFormatException`，命令失败并打印需要手工粘贴的内容，不猜测位置；登记是幂等的。
- **提示只列出还没做的事**：Policy 的权限码已在 `menus.php` 中声明时不提示，控制器已在 `routes.php` 中登记时不打印路由。

## 完整增删改查（`core:make-crud`）

`core:make-crud <模型> --module=<模块> [--app=admin] [--soft-deletes] [--export] [--audit] [--no-data-scope] [-f]`（`core:make-module <模块> --crud=<模型>` 先建模块再调用它），生成清单与设计决定见设计文档 §10.4。依次：

1. `MenusFile`：菜单节点写入模块的 `resources/menus.php`（没有时按模板创建并在 Provider 中登记）；
2. `RoutesFile`：路由写入入口 `routes.php` 中需要登录的分组里的模块分组；
3. `core:make-model`（迁移、工厂、Policy、语言包、多态别名）→ `core:make-action` → `core:make-query` → `core:make-exporter` → `core:make-request` → `core:make-resource` → `core:make-controller`；
4. 授权测试（`crud.test.*` 模板）与模块 README 中该模型的一节（`crud.readme`，插在“删除本模块”之前）。

字段只有占位字段 `name`；加字段时要改的地方写在生成的 README 一节中。

## 测试

- `tests/Core/Generator`：在系统临时目录的最小项目骨架（`TemporaryProject`）中执行各命令，检查生成的文件、共享文件的登记与幂等，以及生成物符合 Pint。
- `tests/Core/Generator/EndToEndTest.php`（`generator-e2e` 组，`composer test:generator-e2e`，CI 中单独一步）：在项目副本（`ProjectCopy`，`vendor` 用硬链接）中执行 `--crud` 的三种组合，跑 Pint、PHPStan、Rector、全部 Pest 测试、`migrate:fresh --seed` 与 `core:sync`，结束后删除副本。修改模板后必须跑一次。
- 测试中当作“类名”的字符串不要用真实存在、或会被端到端检查生成的类（如 `App\Modules\Content\…`、`App\Modules\Blog\…`）：Rector 会把它们改写为 `::class`。

## 对外提供

只提供命令，没有供其他模块调用的类。

## 依赖 Core 的能力

不调用 Core 的任何能力。生成的代码以文本引用 `App\Core\System`（`MenuRegistry`、`ExporterRegistry`、用户模型等）与 `App\Support`（`HasLabel`、`UniqueConstraint`、`BusinessRuleViolation`）；生成器自身只用到这些类的类名（`::class`，编辑 Provider 的导入、默认的用户模型）。
