<?php

namespace App\Modules\Content\Policies;

use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use Illuminate\Auth\Access\Response;

/**
 * Article（文章）的授权规则（设计文档 §6.11、§8.3）：标准方法齐全，默认拒绝。
 *
 * 权限码在模块的 resources/menus.php 中声明：菜单节点 `content.article` 是查看 / 列表权限，按钮 `content.article.create`、`.update`、
 * `.delete`（软删除）、`.publish`（发布与取消发布）、`.restore`、`.force-delete`、`.export`（导出由入口直接声明权限码，§6.10）。
 * 标准写法：先检查权限码，再做业务保护，最后检查数据范围（isVisibleTo，按 created_by，§8.4）；不通过时返回 Response::deny()（403）。
 *
 * 403 / 422 的分工（§6.1、§8.6）：这里判定只看“操作者 + 文章本身”的规则——只有草稿能发布、只有已发布的能取消发布、
 * 只有已删除的能恢复与彻底删除；分类、封面不合法与并发时状态已变由 Form Request / Action 返回 422。
 *
 * 软删除整套（§10.1）：默认的路由绑定不含已删除的文章；restore、forceDelete 的路由带 withTrashed()；
 * 列表与导出带 filter[trashed] 时还要 viewTrashed（能恢复或彻底删除的人才看得到已删除的文章，ArticleList 中检查）。
 *
 * 规则与调用方无关：禁止判断是哪个入口在调用。
 */
class ArticlePolicy
{
    /**
     * Determine whether the user can view any models. 列表另按 visibleTo($actor) 过滤。
     */
    public function viewAny(User $actor): Response
    {
        return $actor->can('content.article') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can view the model（详情；也是封面附件访问权的委托对象，§6.8）。
     */
    public function view(User $actor, Article $article): Response
    {
        if (! $actor->can('content.article')) {
            return Response::deny();
        }

        return $article->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $actor): Response
    {
        return $actor->can('content.article.create') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can update the model. 任何状态都可以修改。
     */
    public function update(User $actor, Article $article): Response
    {
        if (! $actor->can('content.article.update')) {
            return Response::deny();
        }

        return $article->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can delete the model（软删除）。任何状态都可以删除；已删除的再删除由彻底删除处理。
     */
    public function delete(User $actor, Article $article): Response
    {
        if (! $actor->can('content.article.delete') || $article->trashed()) {
            return Response::deny();
        }

        return $article->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * 发布：只有草稿能发布。
     */
    public function publish(User $actor, Article $article): Response
    {
        if (! $actor->can('content.article.publish')) {
            return Response::deny();
        }

        if (! $article->isDraft()) {
            return Response::deny(__('content::article.messages.publish_draft_only'));
        }

        return $article->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * 取消发布：只有已发布的能取消发布（与发布共用权限码）。
     */
    public function unpublish(User $actor, Article $article): Response
    {
        if (! $actor->can('content.article.publish')) {
            return Response::deny();
        }

        if (! $article->isPublished()) {
            return Response::deny(__('content::article.messages.unpublish_published_only'));
        }

        return $article->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can restore the model. 只有已删除的能恢复。
     */
    public function restore(User $actor, Article $article): Response
    {
        if (! $actor->can('content.article.restore')) {
            return Response::deny();
        }

        if (! $article->trashed()) {
            return Response::deny(__('content::article.messages.restore_trashed_only'));
        }

        return $article->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can permanently delete the model. 只有已删除的能彻底删除（先删除，两步操作避免误删）。
     */
    public function forceDelete(User $actor, Article $article): Response
    {
        if (! $actor->can('content.article.force-delete')) {
            return Response::deny();
        }

        if (! $article->trashed()) {
            return Response::deny(__('content::article.messages.force_delete_trashed_only'));
        }

        return $article->isVisibleTo($actor) ? Response::allow() : Response::deny();
    }

    /**
     * 查看已删除的文章（列表与导出的 filter[trashed]）：能恢复或彻底删除的人。
     */
    public function viewTrashed(User $actor): Response
    {
        return $actor->can('content.article.restore') || $actor->can('content.article.force-delete')
            ? Response::allow()
            : Response::deny(__('content::article.messages.view_trashed'));
    }

    /**
     * Determine whether the user can delete models in bulk（目前没有批量接口）。
     */
    public function deleteAny(User $actor): Response
    {
        return $actor->can('content.article.delete') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can restore models in bulk（目前没有批量接口）。
     */
    public function restoreAny(User $actor): Response
    {
        return $actor->can('content.article.restore') ? Response::allow() : Response::deny();
    }

    /**
     * Determine whether the user can permanently delete models in bulk（目前没有批量接口）。
     */
    public function forceDeleteAny(User $actor): Response
    {
        return $actor->can('content.article.force-delete') ? Response::allow() : Response::deny();
    }
}
