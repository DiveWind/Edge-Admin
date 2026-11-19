<?php

namespace App\Http\Admin\Content\Controllers;

use App\Core\System\Models\User;
use App\Modules\Content\Actions\ForceDeleteArticle;
use App\Modules\Content\Models\Article;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 彻底删除已删除的文章（DELETE /content/articles/{article}/force-delete，设计文档 §6.11、§10.1）：记录与封面文件一起删除，返回 data: null。
 * 路由带 withTrashed()；只有已删除的能彻底删除（ArticlePolicy::forceDelete，403：先删除；并发时 422）。
 */
class ForceDeleteArticleController
{
    #[Authorize('forceDelete', 'article')]
    public function __invoke(Article $article, ForceDeleteArticle $action, #[CurrentUser] User $actor): void
    {
        $action->handle($article, $actor);
    }
}
