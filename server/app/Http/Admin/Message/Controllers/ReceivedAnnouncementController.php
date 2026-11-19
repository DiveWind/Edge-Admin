<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Models\Announcement;
use App\Core\System\Models\User;
use App\Http\Admin\Message\Requests\ReceivedAnnouncementIndexRequest;
use App\Http\Admin\Message\Resources\ReceivedAnnouncementResource;
use App\Support\QueryBuilder\Filters\DateTimeRangeFilter;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

/**
 * 我收到的公告（设计文档 §6.9）：已发布、且目标包含当前用户（全部用户，或当前拥有任一启用的目标角色）的公告。
 *
 * 列表只需登录（LOGIN_ONLY_ROUTES），只查发给本人的（Announcement::receivedBy()）；详情由 AnnouncementPolicy::read 判定
 * （草稿、已撤回、不是发给自己的 403）。公告通知的 link 指向前端的公告详情页，页面调用详情接口。
 */
class ReceivedAnnouncementController
{
    /**
     * 列表：筛选 title（模糊）、level（精确）、published_at（区间）；排序 published_at，默认 `-published_at`。
     */
    public function index(ReceivedAnnouncementIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $announcements = QueryBuilder::for(Announcement::query()->receivedBy($actor)->with('publisher'), $request)
            ->allowedFilters(
                AllowedFilter::partial('title'),
                AllowedFilter::exact('level'),
                DateTimeRangeFilter::allowed('published_at'),
            )
            ->allowedSorts('published_at')
            ->defaultSort('-published_at')
            ->paginate($request->pageSize());

        return ReceivedAnnouncementResource::collection($announcements);
    }

    /**
     * 详情，含 content 与 attachments（附件的签名地址由本接口签发）。
     */
    #[Authorize('read', 'announcement')]
    public function show(Announcement $announcement): ReceivedAnnouncementResource
    {
        return new ReceivedAnnouncementResource($announcement->load(['publisher', 'files']))->withDetails();
    }
}
