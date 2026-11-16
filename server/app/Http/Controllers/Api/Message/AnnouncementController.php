<?php

namespace App\Http\Controllers\Api\Message;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\AnnouncementIndexRequest;
use App\Http\Requests\Message\AnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Jobs\SendAnnouncementNotifications;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AnnouncementController extends Controller
{
    public function index(AnnouncementIndexRequest $request): AnonymousResourceCollection
    {
        $query = Announcement::query()->with('creator')->filter($request->filters());

        return AnnouncementResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(AnnouncementRequest $request, #[CurrentUser] User $actor): AnnouncementResource
    {
        $announcement = new Announcement($request->validated());
        $announcement->created_by = $actor->id;
        $announcement->save();

        return new AnnouncementResource($announcement->load('creator'));
    }

    public function show(Announcement $announcement): AnnouncementResource
    {
        return new AnnouncementResource($announcement->load('creator'));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): AnnouncementResource
    {
        $announcement->update($request->validated());

        return new AnnouncementResource($announcement->load('creator'));
    }

    public function destroy(Announcement $announcement): Response
    {
        $announcement->delete();

        return response()->noContent();
    }

    /**
     * 发布公告，并通过队列给所有启用用户发送站内通知。
     */
    public function publish(Announcement $announcement): AnnouncementResource
    {
        Gate::authorize('publish', $announcement);

        $announcement->markAsPublished();

        SendAnnouncementNotifications::dispatch($announcement);

        return new AnnouncementResource($announcement->load('creator'));
    }
}
