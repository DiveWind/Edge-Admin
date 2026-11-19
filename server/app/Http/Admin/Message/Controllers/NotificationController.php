<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Actions\DeleteNotification;
use App\Core\Message\Models\Notification;
use App\Core\System\Models\User;
use App\Http\Admin\Message\Requests\NotificationIndexRequest;
use App\Http\Admin\Message\Resources\NotificationResource;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

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
    public function index(NotificationIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $notifications = QueryBuilder::for(Notification::query()->ownedBy($actor), $request)
            ->allowedFilters(
                AllowedFilter::callback('is_read', fn (Builder $query, mixed $value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)
                    ? $query->whereNotNull('read_at')
                    : $query->whereNull('read_at')),
                AllowedFilter::exact('type'),
                DateTimeRangeFilter::allowed('created_at'),
            )
            ->allowedSorts('created_at', 'read_at')
            ->defaultSort('-created_at')
            ->paginate($request->pageSize());

        return NotificationResource::collection($notifications);
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
