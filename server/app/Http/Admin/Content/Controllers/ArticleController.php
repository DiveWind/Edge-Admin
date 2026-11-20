<?php

namespace App\Http\Admin\Content\Controllers;

use App\Core\System\Models\User;
use App\Http\Admin\Content\Requests\{ArticleIndexRequest, ArticleRequest};
use App\Http\Admin\Content\Resources\ArticleResource;
use App\Modules\Content\Actions\{CreateArticle, DeleteArticle, UpdateArticle};
use App\Modules\Content\Models\Article;
use App\Modules\Content\Queries\ArticleList;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 文章管理（Admin 入口，设计文档 §6.11）：列表、详情、新增、修改、删除（软删除）。
 * 发布、取消发布、恢复、彻底删除、导出是单动作控制器。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 ArticlePolicy 判定（默认拒绝，含数据范围），
 * 写接口调用 Content 模块的 Action。路由登记在 Admin 入口的 routes.php。
 */
class ArticleController
{
    /**
     * 列表（经过数据范围 visibleTo($actor)）：筛选 title（模糊）、category、status（精确）、published_at、created_at（区间）、
     * trashed（with / only，另需 viewTrashed）；排序 id、published_at、created_at，默认 `-id`。不在白名单内的参数返回 422（§6.3）。
     * 查询在 Query 类 ArticleList 中，与导出（ExportArticlesController）共用。
     */
    #[Authorize('viewAny', Article::class)]
    public function index(ArticleIndexRequest $request, ArticleList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return ArticleResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 详情，含 content 与 cover。已删除的文章为 404（路由绑定不含已删除的）。
     */
    #[Authorize('view', 'article')]
    public function show(Article $article): ArticleResource
    {
        return new ArticleResource($article->load(ArticleResource::relations()))->withDetails();
    }

    /**
     * 新增草稿（201）：created_by 为操作者。
     */
    #[Authorize('create', Article::class)]
    public function store(ArticleRequest $request, CreateArticle $action, #[CurrentUser] User $actor): ArticleResource
    {
        $article = $action->handle($request->validated(), $actor);

        return new ArticleResource($article->load(ArticleResource::relations()))->withDetails();
    }

    /**
     * 修改：任何状态都可以修改；未提交 cover_id 表示封面不变。
     */
    #[Authorize('update', 'article')]
    public function update(ArticleRequest $request, Article $article, UpdateArticle $action, #[CurrentUser] User $actor): ArticleResource
    {
        $article = $action->handle($article, $request->validated(), $actor);

        return new ArticleResource($article->load(ArticleResource::relations()))->withDetails();
    }

    /**
     * 删除（软删除，可恢复）：封面保留。
     */
    #[Authorize('delete', 'article')]
    public function destroy(Article $article, DeleteArticle $action, #[CurrentUser] User $actor): void
    {
        $action->handle($article, $actor);
    }
}
