# Generator

内核模块，目录 `app/Core/Generator`：`core:make-*` 代码生成命令（设计文档 §10.4）。只在命令行下注册，不影响 HTTP 请求。

## 职责

按本项目的目录结构与规范生成文件，并幂等地登记到共享文件；生成物直接通过 Pint、Rector、PHPStan 与架构测试。
用法见 [`server/README.md`](../../../README.md#代码生成) 与 `php artisan list core`。

| 目录 | 内容 |
|---|---|
| `Commands/` | 各命令；基类 `CoreGeneratorCommand`（模板、名称、导入、退出码）、`ModuleGeneratorCommand`（`--module`、`--core`）、`EntryGeneratorCommand`（`--app`） |
| `Editing/` | 修改共享文件：`bootstrap/providers.php`、`config/core.php`、`phpunit.xml`、`.env.example`、模块 Provider；`PhpImports` 按 Pint 的格式输出导入 |
| `stubs/` | 默认模板。项目的 `stubs/core.<名称>.stub` 优先（`php artisan stub:publish` 或 `vendor:publish --tag=core-stubs` 发布） |

## 对外提供

只提供命令，没有供其他模块调用的类。

## 依赖 Core 的能力

无。生成的代码以文本引用 `App\Core\System`（`HasLabel`、用户模型），不产生代码依赖。

## 扩展：完整增删改查（`core:make-module --crud`）

尚未实现，依赖 Core 的扩展点（菜单与权限节点等，尚未提供）。接入方式：在 `ModuleMakeCommand` 中去掉 `--crud` 的提示，
依次调用 `core:make-model`、`core:make-action`（Create / Update / Delete）、`core:make-request`、`core:make-resource`、
`core:make-controller`，再通过新的 `Editing/*File` 登记权限节点与路由，并按模板生成授权测试。
