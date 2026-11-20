<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Actions\DeleteNotification;
use App\Core\Message\Models\Notification;
use App\Core\Message\Queries\NotificationList;
use App\Core\System\Models\User;
use App\Http\Admin\Message\Requests\NotificationIndexRequest;
use App\Http\Admin\Message\Resources\NotificationResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 我的通知（设计文档 §6.9）：列表与删除。标记已读、全部已读、未读数是单动作控制器。
 *
 * 列表只需登录（LOGIN_ONLY_ROUTES），只查本人的通知；删除由 NotificationPolicy 判定只能删除本人的（403）。写入调用 Message 模块的 Action。
 */
class NotificationController
{
    /**
     * 列表（只有本人的）：筛选 is_read（true / false）、type（精确，逗号分隔多个）、created_at（区间 `起,止`）；
     * 排序 created_at、read_at，默认 `-created_at`。不在白名单内的参数返回 422（§6.3）。
     */
    public function index(NotificationIndexRequest $request, NotificationList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return NotificationResource::collection($list->builder($actor, $request->listParameters())->paginate($request->pageSize()));
    }

    /**
     * 删除一条通知（只能删除本人的）。
     */
    #[Authorize('delete', 'notification')]
    public function destroy(Notification $notification, DeleteNotification $action): void
    {
        $action->handle($notification);
    }
}
