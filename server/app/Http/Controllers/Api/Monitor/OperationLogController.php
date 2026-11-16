<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitor\OperationLogIndexRequest;
use App\Http\Resources\OperationLogResource;
use App\Models\OperationLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OperationLogController extends Controller
{
    public function index(OperationLogIndexRequest $request): AnonymousResourceCollection
    {
        $query = OperationLog::query()->with('user')->filter($request->filters());

        return OperationLogResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function show(OperationLog $operationLog): OperationLogResource
    {
        return new OperationLogResource($operationLog->load('user'));
    }

    public function destroy(OperationLog $operationLog): Response
    {
        $operationLog->delete();

        return response()->noContent();
    }

    /**
     * 清空操作日志。
     */
    public function clear(): Response
    {
        OperationLog::query()->delete();

        return response()->noContent();
    }
}
