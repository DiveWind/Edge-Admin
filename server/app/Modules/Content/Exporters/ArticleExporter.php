<?php

namespace App\Modules\Content\Exporters;

use App\Core\System\Contracts\Exporter;
use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use App\Modules\Content\Queries\ArticleList;
use Illuminate\Database\Eloquent\{Builder, Model};

/**
 * 文章列表的导出（对外名称 `content.article`，POST /content/articles/export，设计文档 §6.10、§6.11）。
 *
 * 查询就是文章列表的 ArticleList：同一份筛选与排序白名单、同一个数据范围（visibleTo），含 filter[trashed] 的授权（viewTrashed），
 * 导出的文章不会比列表多。显示名与表头是本模块语言包（content::article.export）中的键，生成文件时按发起人的语言翻译。
 *
 * 列：id、title、category（字典的 value：字典的显示名是前端 i18n key 或管理员录入的文本，后端不翻译）、status（显示名）、
 * published_at、creator（创建人的用户名）、created_at、deleted_at（时间为应用时区的 Y-m-d H:i:s）。
 *
 * @implements Exporter<Article>
 */
class ArticleExporter implements Exporter
{
    public function __construct(private readonly ArticleList $list) {}

    /**
     * 导出的显示名。
     */
    public function title(): string
    {
        return 'content::article.export.title';
    }

    /**
     * 导出的权限码。
     */
    public function permission(): string
    {
        return 'content.article.export';
    }

    /**
     * 列与表头。
     *
     * @return array<string, string>
     */
    public function columns(): array
    {
        $columns = ['id', 'title', 'category', 'status', 'published_at', 'creator', 'created_at', 'deleted_at'];

        return array_combine($columns, array_map(fn (string $column): string => "content::article.export.columns.{$column}", $columns));
    }

    /**
     * 文章列表的查询。
     *
     * @param  array<string, mixed>  $parameters
     * @return Builder<Article>
     */
    public function query(User $actor, array $parameters): Builder
    {
        return $this->list->builder($actor, $parameters)->getEloquentBuilder();
    }

    /**
     * 一行。
     *
     * @return array<string, string|int|null>
     */
    public function map(Model $row): array
    {
        return [
            'id' => $row->id,
            'title' => $row->title,
            'category' => $row->category,
            'status' => $row->status->label(),
            'published_at' => $row->published_at?->format('Y-m-d H:i:s'),
            'creator' => $row->creator?->username,
            'created_at' => $row->created_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $row->deleted_at?->format('Y-m-d H:i:s'),
        ];
    }
}
