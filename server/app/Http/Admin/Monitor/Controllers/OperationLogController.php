<?php

namespace App\Http\Admin\Monitor\Controllers;

use App\Core\Monitor\Models\OperationLog;
use App\Core\Monitor\Queries\OperationLogList;
use App\Core\System\Models\User;
use App\Http\Admin\Monitor\Requests\OperationLogIndexRequest;
use App\Http\Admin\Monitor\Resources\OperationLogResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 操作日志（设计文档 §6.6）：列表、详情。只读：没有新增、修改、删除接口，过期日志由 activitylog:clean 清理。
 *
 * 授权由 OperationLogPolicy 判定（monitor.operation-log；数据范围委托给操作者）。
 */
class OperationLogController
{
    /**
     * 列表（经过数据范围 visibleTo($actor)，不含 route_parameters、payload）：筛选 user_id、method、status、request_id（精确），
     * username、description、ip_address（模糊），created_at（区间 `起,止`）；排序 id、created_at，默认 `-id`。
     * status 在 JSON 中是整数，按整数比较（SQLite 的 json_extract 返回整数，与字符串不相等）。
     */
    #[Authorize('viewAny', OperationLog::class)]
    public function index(OperationLogIndexRequest $request, OperationLogList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return OperationLogResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 详情，含 route_parameters 与 payload（已脱敏）。
     */
    #[Authorize('view', 'operation_log')]
    public function show(OperationLog $operationLog): OperationLogResource
    {
        return new OperationLogResource($operationLog)->withDetails();
    }
}
