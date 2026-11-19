<?php

namespace App\Http\Admin\System\Controllers;

use App\Core\System\Actions\CreateExport;
use App\Core\System\Models\User;
use App\Http\Admin\System\Requests\UserExportRequest;
use App\Http\Admin\System\Resources\ExportResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 导出用户列表（POST /system/users/export，设计文档 §6.10）：创建导出任务并入队，返回导出记录（201）。
 *
 * 权限码 system.user.export（Gate::before，执行时再复核）；筛选与排序与列表相同（UserList 的白名单与数据范围），不合法 422；
 * 同时进行的导出超过上限 422（CreateExport）；路由另经过 admin-exports 限流。文件在“我的导出”（/exports）中下载。
 */
class UserExportController
{
    #[Authorize('system.user.export')]
    public function __invoke(UserExportRequest $request, CreateExport $action, #[CurrentUser] User $actor): ExportResource
    {
        return new ExportResource($action->handle($actor, 'system.user', $request->exportFormat(), $request->listParameters()));
    }
}
