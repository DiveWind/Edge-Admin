<?php

namespace App\Core\Message\Queries;

use App\Core\Message\Models\Announcement;
use App\Core\System\Models\User;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 我收到的公告（GET /announcements，设计文档 §6.9）：只含发给 $actor 的已发布公告（receivedBy()），预加载 publisher。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class ReceivedAnnouncementList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<Announcement>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(Announcement::query()->receivedBy($actor)->with('publisher'), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::partial('title'),
                AllowedFilter::exact('level'),
                DateTimeRangeFilter::allowed('published_at'),
            )
            ->allowedSorts('published_at')
            ->defaultSort('-published_at');
    }
}
