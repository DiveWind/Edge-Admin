<?php

namespace App\Core\System\Queries;

use App\Core\System\Models\{Role, User};
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 角色管理的列表查询（GET /system/roles，设计文档 §6.5）：不受数据范围限制（能否修改由 RolePolicy 判定）。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class RoleList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<Role>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(Role::query(), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('title'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('sort', 'id', 'created_at')
            ->defaultSort('sort', 'id');
    }
}
