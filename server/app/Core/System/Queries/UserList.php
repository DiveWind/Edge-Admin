<?php

namespace App\Core\System\Queries;

use App\Core\System\Models\User;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 用户管理的列表查询（GET /system/users，设计文档 §6.5、§6.10）：列表接口与导出（UserExporter）共用，导出看到的数据不会比列表多。
 *
 * - 经过数据范围 visibleTo($actor)（§8.4），预加载 roles；
 * - 筛选 username、name、email（模糊）、status（精确）、created_at（区间 `起,止`，DateTimeRangeFilter）；
 * - 排序 id、username、created_at，默认 `-id`；
 * - 不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery（后台入口映射为 422，§6.3），时间区间格式错误抛出 ValidationException（422）。
 *
 * 参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()，或导出记录中保存的 params），同一份参数可以在队列中重放。
 */
class UserList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()（列表）或 ->getEloquentBuilder()（导出）。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<User>
     */
    public function builder(User $actor, array $parameters): QueryBuilder
    {
        return QueryBuilder::for(User::query()->visibleTo($actor)->with('roles'), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::partial('username'),
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::exact('status'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('id', 'username', 'created_at')
            ->defaultSort('-id');
    }
}
