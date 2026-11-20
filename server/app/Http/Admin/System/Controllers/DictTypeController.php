<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\{CreateDictType, DeleteDictType, UpdateDictType};
use App\Core\System\Models\{DictType, User};
use App\Core\System\Queries\DictTypeList;
use App\Http\Admin\System\Requests\{DictTypeIndexRequest, DictTypeRequest};
use App\Http\Admin\System\Resources\DictTypeResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 字典类型管理（设计文档 §6.7、§8.6）：列表、详情、新增、修改、删除。字典项见 DictItemController。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 DictTypePolicy 判定（默认拒绝，删除内置类型 403），
 * 写入调用 System 模块的 Action（内置类型的 code 保护、唯一性在 Action 中）。
 */
class DictTypeController
{
    /**
     * 列表：筛选 code、name（模糊）、status（精确）；排序 id、code、created_at，默认 id。
     * 不在白名单内的筛选、排序参数返回 422（§6.3）。
     */
    #[Authorize('viewAny', DictType::class)]
    public function index(DictTypeIndexRequest $request, DictTypeList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return DictTypeResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 详情。
     */
    #[Authorize('view', 'dict_type')]
    public function show(DictType $dictType): DictTypeResource
    {
        return new DictTypeResource($dictType);
    }

    /**
     * 新增自定义类型（201）。
     */
    #[Authorize('create', DictType::class)]
    public function store(DictTypeRequest $request, CreateDictType $action): DictTypeResource
    {
        return new DictTypeResource($action->handle($request->validated()));
    }

    /**
     * 修改：内置类型的 code 不能修改（422）。
     */
    #[Authorize('update', 'dict_type')]
    public function update(DictTypeRequest $request, DictType $dictType, UpdateDictType $action): DictTypeResource
    {
        return new DictTypeResource($action->handle($dictType, $request->validated()));
    }

    /**
     * 删除，连同其下的字典项：内置类型 403（Policy）。
     */
    #[Authorize('delete', 'dict_type')]
    public function destroy(DictType $dictType, DeleteDictType $action): void
    {
        $action->handle($dictType);
    }
}
