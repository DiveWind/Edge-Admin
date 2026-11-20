<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\{CreateDictItem, DeleteDictItem, UpdateDictItem};
use App\Core\System\Models\{DictItem, DictType};
use App\Core\System\Queries\DictItemList;
use App\Http\Admin\System\Requests\{DictItemIndexRequest, DictItemRequest};
use App\Http\Admin\System\Resources\DictItemResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 字典项管理（设计文档 §6.7、§8.6）：某个类型的字典项列表与新增（/system/dict-types/{dict_type}/items），
 * 修改与删除（/system/dict-items/{dict_item}）。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 DictItemPolicy 判定（默认拒绝，删除内置字典项 403），
 * 写入调用 System 模块的 Action（内置字典项的 value 保护、唯一性在 Action 中）。
 */
class DictItemController
{
    /**
     * 该类型的字典项列表（含停用项）：筛选 value、label（模糊）、status（精确）；排序 sort、id、value，默认 `sort,id`。
     */
    #[Authorize('viewAny', DictItem::class)]
    public function index(DictItemIndexRequest $request, DictType $dictType, DictItemList $list): AnonymousResourceCollection
    {
        return DictItemResource::collection($list->builder($dictType, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 新增自定义字典项（201）。
     */
    #[Authorize('create', DictItem::class)]
    public function store(DictItemRequest $request, DictType $dictType, CreateDictItem $action): DictItemResource
    {
        return new DictItemResource($action->handle($dictType, $request->validated()));
    }

    /**
     * 修改：内置字典项的 value 不能修改（422）。
     */
    #[Authorize('update', 'dict_item')]
    public function update(DictItemRequest $request, DictItem $dictItem, UpdateDictItem $action): DictItemResource
    {
        return new DictItemResource($action->handle($dictItem, $request->validated()));
    }

    /**
     * 删除：内置字典项 403（Policy）。
     */
    #[Authorize('delete', 'dict_item')]
    public function destroy(DictItem $dictItem, DeleteDictItem $action): void
    {
        $action->handle($dictItem);
    }
}
