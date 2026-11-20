<?php

namespace App\Core\System\Queries;

use App\Core\System\Models\{Export, User};
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 我的导出（GET /exports，设计文档 §6.10）：只含 $actor 自己发起的导出（ownedBy()），预加载 file。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class ExportList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<Export>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(Export::query()->ownedBy($actor)->with('file'), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::exact('name', 'exporter'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('format'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id', 'created_at')
            ->defaultSort('-id');
    }
}
