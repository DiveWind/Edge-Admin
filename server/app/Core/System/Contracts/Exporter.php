<?php

namespace App\Core\System\Contracts;

use App\Core\System\Models\User;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * 导出类（Core 的扩展点“异步导出”，设计文档 §5.5、§6.10）：模块实现本接口并在 Provider 的 boot() 中登记，即接入统一的
 * 异步导出（队列、simple-excel 流式写出）、完成通知与下载（附件的签名地址）：
 *
 * ```php
 * public function boot(ExporterRegistry $exporters): void
 * {
 *     $exporters->register(['content.article' => ArticleExporter::class]);
 * }
 * ```
 *
 * 再由列表提供导出入口（`POST /<列表路径>/export`，权限码 `<菜单>.export`），调用 CreateExport。
 *
 * - query() **必须调用该列表所用的 Query 类**（如 UserList）：同一份 query-builder 白名单与数据范围（visibleTo），导出看到的数据不会比列表多；
 * - 由容器解析，实现必须无状态（常驻内存安全，§10.3）；
 * - title()、columns() 的表头是后端文案（JSON 语言包，英文原文为键），生成文件时按发起人的语言翻译；map() 中的显示名（枚举的 label() 等）同样按该语言。
 *
 * @template TModel of Model
 */
interface Exporter
{
    /**
     * 导出的显示名（后端文案），用于文件名与接口的 title，如 `Users`。
     */
    public function title(): string;

    /**
     * 导出的权限码（列表菜单下的 `<菜单>.export` 按钮，须在 resources/menus.php 中声明）：导出入口声明同一个码，执行时再按它复核。
     */
    public function permission(): string;

    /**
     * 列：列键 => 表头（后端文案）。文件按这个顺序输出列。
     *
     * @return array<string, string>
     */
    public function columns(): array;

    /**
     * 按“操作者 + 列表参数”构造查询（不执行）：调用该列表的 Query 类，参数不在白名单内时抛出与列表相同的异常（422）。
     *
     * @param  array<string, mixed>  $parameters  列表参数 `{ filter, sort }`（ListRequest::listParameters()）
     * @return Builder<TModel>
     */
    public function query(User $actor, array $parameters): Builder;

    /**
     * 一行：列键 => 标量或 null（按 columns() 的顺序取值，缺少的列为空）。
     *
     * @param  TModel  $row
     * @return array<string, string|int|float|bool|null>
     */
    public function map(Model $row): array;
}
