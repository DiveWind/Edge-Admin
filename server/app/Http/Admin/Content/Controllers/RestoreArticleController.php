<?php

namespace App\Http\Admin\Content\Controllers;

use App\Core\System\Models\User;
use App\Http\Admin\Content\Resources\ArticleResource;
use App\Modules\Content\Actions\RestoreArticle;
use App\Modules\Content\Models\Article;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 恢复已删除的文章（POST /content/articles/{article}/restore，设计文档 §6.11、§10.1）：返回恢复后的文章。
 * 路由带 withTrashed()；只有已删除的能恢复（ArticlePolicy::restore，403；并发时 422）。
 */
class RestoreArticleController
{
    #[Authorize('restore', 'article')]
    public function __invoke(Article $article, RestoreArticle $action, #[CurrentUser] User $actor): ArticleResource
    {
        $article = $action->handle($article, $actor);

        return new ArticleResource($article->load(ArticleResource::relations()))->withDetails();
    }
}
