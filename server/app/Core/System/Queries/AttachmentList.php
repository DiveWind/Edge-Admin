<?php

namespace App\Core\System\Queries;

use App\Core\System\Models\{Attachment, User};
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 附件管理的列表查询（GET /system/attachments，设计文档 §6.8）：经过数据范围 visibleTo($actor)（按 created_by，§8.4），不含私有绑定的附件（withoutPrivate()，如导出文件），预加载 creator。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class AttachmentList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<Attachment>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(Attachment::query()->visibleTo($actor)->withoutPrivate()->with('creator'), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('mime_type'),
                AllowedFilter::exact('created_by'),
                AllowedFilter::exact('collection'),
                AllowedFilter::callback('is_bound', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereNotNull('attachable_type')
                    : $query->whereNull('attachable_type')),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id', 'size', 'created_at')
            ->defaultSort('-id');
    }
}
