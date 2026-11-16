<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\SettingIndexRequest;
use App\Http\Requests\System\StoreSettingRequest;
use App\Http\Requests\System\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * 系统参数；参数缓存由模型事件自动失效。
 */
class SettingController extends Controller
{
    public function index(SettingIndexRequest $request): AnonymousResourceCollection
    {
        $query = Setting::query()->filter($request->filters());

        return SettingResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(StoreSettingRequest $request): SettingResource
    {
        return new SettingResource(Setting::query()->create($request->validated()));
    }

    public function show(Setting $setting): SettingResource
    {
        return new SettingResource($setting);
    }

    public function update(UpdateSettingRequest $request, Setting $setting): SettingResource
    {
        $setting->update($request->validated());

        return new SettingResource($setting);
    }

    /**
     * 内置参数（is_system）不可删除（403）。
     */
    public function destroy(Setting $setting): Response
    {
        Gate::authorize('delete', $setting);

        $setting->delete();

        return response()->noContent();
    }
}
