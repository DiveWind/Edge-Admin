<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\DictItemIndexRequest;
use App\Http\Requests\System\DictItemRequest;
use App\Http\Resources\DictItemResource;
use App\Models\DictItem;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 数据字典项；字典缓存由模型事件自动失效。
 */
class DictItemController extends Controller
{
    public function index(DictItemIndexRequest $request): AnonymousResourceCollection
    {
        $query = DictItem::query()->filter($request->filters());

        return DictItemResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(DictItemRequest $request): DictItemResource
    {
        return new DictItemResource(DictItem::query()->create($request->validated()));
    }

    public function show(DictItem $dictItem): DictItemResource
    {
        return new DictItemResource($dictItem);
    }

    public function update(DictItemRequest $request, DictItem $dictItem): DictItemResource
    {
        $dictItem->update($request->validated());

        return new DictItemResource($dictItem);
    }

    public function destroy(DictItem $dictItem): Response
    {
        $dictItem->delete();

        return response()->noContent();
    }
}
