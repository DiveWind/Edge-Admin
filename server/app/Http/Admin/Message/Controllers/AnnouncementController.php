<?php

namespace App\Http\Admin\Message\Controllers;

use App\Core\Message\Actions\{CreateAnnouncement, DeleteAnnouncement, UpdateAnnouncement};
use App\Core\Message\Models\Announcement;
use App\Core\Message\Queries\AnnouncementList;
use App\Core\System\Models\User;
use App\Http\Admin\Message\Requests\{AnnouncementIndexRequest, AnnouncementRequest};
use App\Http\Admin\Message\Resources\AnnouncementResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 公告管理（设计文档 §6.9、§8.6）：列表、详情、新增、修改、删除。发布、撤回、目标角色下拉是单动作控制器。
 *
 * 控制器只做“授权声明 → 调用 Action / 查询 → 返回 Resource”（设计文档 §5.4）；授权由 AnnouncementPolicy 判定
 * （message.announcement.*、只有草稿能修改、已发布的不能删除、数据范围按 created_by），写入调用 Message 模块的 Action。
 */
class AnnouncementController
{
    /**
     * 列表（经过数据范围 visibleTo($actor)）：筛选 title（模糊）、status、level、target（精确）、created_at、published_at（区间）；
     * 排序 id、created_at、published_at，默认 `-id`。不在白名单内的参数返回 422（§6.3）。
     */
    #[Authorize('viewAny', Announcement::class)]
    public function index(AnnouncementIndexRequest $request, AnnouncementList $list, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        return AnnouncementResource::collection($list->builder($actor, $request->listParameters())->with(AnnouncementResource::relations())->paginate($request->pageSize()));
    }

    /**
     * 详情，含 content 与 attachments。
     */
    #[Authorize('view', 'announcement')]
    public function show(Announcement $announcement): AnnouncementResource
    {
        return new AnnouncementResource($announcement->load(AnnouncementResource::relations(details: true)))->withDetails();
    }

    /**
     * 新增草稿（201）。
     */
    #[Authorize('create', Announcement::class)]
    public function store(AnnouncementRequest $request, CreateAnnouncement $action, #[CurrentUser] User $actor): AnnouncementResource
    {
        $announcement = $action->handle($request->validated(), $actor);

        return new AnnouncementResource($announcement->load(AnnouncementResource::relations(details: true)))->withDetails();
    }

    /**
     * 修改草稿：已发布、已撤回的 403（Policy）。
     */
    #[Authorize('update', 'announcement')]
    public function update(AnnouncementRequest $request, Announcement $announcement, UpdateAnnouncement $action): AnnouncementResource
    {
        $announcement = $action->handle($announcement, $request->validated());

        return new AnnouncementResource($announcement->load(AnnouncementResource::relations(details: true)))->withDetails();
    }

    /**
     * 删除草稿或已撤回的公告（连同附件）：已发布的 403（Policy，先撤回）。
     */
    #[Authorize('delete', 'announcement')]
    public function destroy(Announcement $announcement, DeleteAnnouncement $action): void
    {
        $action->handle($announcement);
    }
}
