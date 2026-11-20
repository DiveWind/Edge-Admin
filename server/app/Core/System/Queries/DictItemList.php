<?php

namespace App\Core\System\Queries;

use App\Core\System\Models\{DictItem, DictType};
use Illuminate\Http\Request;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 某个字典类型的字典项（GET /system/dict-types/{dict_type}/items，设计文档 §6.7）：嵌套列表，以上级记录代替操作者（只看权限码）。
 *
 * 筛选与排序的白名单只在这里（列表请求只校验取值格式）；不在白名单内的筛选、排序抛出 query-builder 的 InvalidQuery
 * （后台入口映射为 422，§6.3）。参数是数组 `{ filter, sort }`（列表请求的 ListRequest::listParameters()）。
 */
class DictItemList
{
    /**
     * 构造查询（不执行）：调用方 ->paginate()。
     *
     * @param  array<string, mixed>  $parameters  `{ filter?: array<string, mixed>, sort?: string }`
     * @return QueryBuilder<DictItem>
     */
    public function builder(DictType $type, array $parameters): QueryBuilder
    {
        return QueryBuilder::for($type->items(), new Request($parameters))
            ->allowedFilters(
                AllowedFilter::partial('value'),
                AllowedFilter::partial('label'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('sort', 'id', 'value')
            ->defaultSort('sort', 'id');
    }
}
