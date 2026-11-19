<?php

namespace App\Modules\Content\Queries;

use App\Core\System\Models\User;
use App\Modules\Content\Models\Article;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 文章管理的列表查询（GET /content/articles，设计文档 §6.11、§6.10）：列表接口与导出（ArticleExporter）共用，导出看到的数据不会比列表多。
 *
 * - 经过数据范围 visibleTo($actor)（按 created_by，§8.4），预加载 creator、cover；
 * - 筛选 title（模糊）、category、status（精确）、published_at、created_at（区间 `起,止`，DateTimeRangeFilter）、
 *   trashed（`with` 含已删除、`only` 只看已删除；query-builder 的 AllowedFilter::trashed()，§10.1）；
 * - 排序 id、published_at、created_at，默认 `-id`；
 * - 不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery（后台入口映射为 422，§6.3），时间区间格式错误抛出 ValidationException（422）；
 * - 带 trashed（with / only）时还要 ArticlePolicy::viewTrashed，否则抛出 AuthorizationException（403）：导出在发起时与队列中执行时同样检查。
 *
 * 参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()，或导出记录中保存的 params），同一份参数可以在队列中重放。
 */
class ArticleList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()（列表）或 ->getEloquentBuilder()（导出）。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<Article>
     *
     * @throws AuthorizationException
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        if ($this->includesTrashed($parameters)) {
            Gate::forUser($actor)->authorize('viewTrashed', Article::class);
        }

        return QueryBuilder::for(Article::query()->visibleTo($actor)->with(['creator', 'cover']), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::partial('title'),
                AllowedFilter::exact('category'),
                AllowedFilter::exact('status'),
                DateTimeRangeFilter::allowed('published_at'),
                DateTimeRangeFilter::allowed('created_at'),
                AllowedFilter::trashed(),
            )
            ->allowedSorts('id', 'published_at', 'created_at')
            ->defaultSort('-id');
    }

    /**
     * 参数中是否要求已删除的文章（filter[trashed] 为 with 或 only）。
     *
     * @param  array<string, mixed>  $parameters
     */
    private function includesTrashed(array $parameters): bool
    {
        $filter = $parameters['filter'] ?? null;

        return is_array($filter) && in_array($filter['trashed'] ?? null, ['with', 'only'], true);
    }
}
