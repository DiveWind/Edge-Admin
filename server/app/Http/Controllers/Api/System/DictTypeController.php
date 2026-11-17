<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\{DictTypeIndexRequest, DictTypeRequest};
use App\Http\Resources\DictTypeResource;
use App\Models\DictType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 数据字典类型；字典缓存由模型事件自动失效。
 */
class DictTypeController extends Controller
{
    #[Authorize('system.dict.view')]
    public function index(DictTypeIndexRequest $request): AnonymousResourceCollection
    {
        $query = DictType::query()->withCount('items')->filter($request->filters());

        return DictTypeResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    #[Authorize('system.dict.create')]
    public function store(DictTypeRequest $request): DictTypeResource
    {
        return new DictTypeResource(DictType::query()->create($request->validated()));
    }

    #[Authorize('system.dict.view')]
    public function show(DictType $dictType): DictTypeResource
    {
        return new DictTypeResource($dictType->loadCount('items'));
    }

    #[Authorize('system.dict.update')]
    public function update(DictTypeRequest $request, DictType $dictType): DictTypeResource
    {
        $dictType->update($request->validated());

        return new DictTypeResource($dictType);
    }

    /**
     * 存在字典项时不可删除（403），需先删除字典项。
     */
    #[Authorize('system.dict.delete')]
    #[Authorize('delete', 'dict_type')]
    public function destroy(DictType $dictType): Response
    {
        $dictType->delete();

        return response()->noContent();
    }
}
