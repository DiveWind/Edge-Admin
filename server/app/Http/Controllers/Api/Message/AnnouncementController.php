<?php

namespace App\Http\Controllers\Api\Message;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\{AnnouncementIndexRequest, AnnouncementRequest};
use App\Http\Resources\AnnouncementResource;
use App\Models\{Announcement, User};
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class AnnouncementController extends Controller
{
    #[Authorize('message.announcement.view')]
    public function index(AnnouncementIndexRequest $request): AnonymousResourceCollection
    {
        $query = Announcement::query()->with('creator')->filter($request->filters());

        return AnnouncementResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    #[Authorize('message.announcement.create')]
    public function store(AnnouncementRequest $request, #[CurrentUser] User $actor): AnnouncementResource
    {
        $announcement = new Announcement($request->validated());
        $announcement->created_by = $actor->id;
        $announcement->save();

        return new AnnouncementResource($announcement->load('creator'));
    }

    #[Authorize('message.announcement.view')]
    public function show(Announcement $announcement): AnnouncementResource
    {
        return new AnnouncementResource($announcement->load('creator'));
    }

    #[Authorize('message.announcement.update')]
    #[Authorize('update', 'announcement')]
    public function update(AnnouncementRequest $request, Announcement $announcement): AnnouncementResource
    {
        $announcement->update($request->validated());

        return new AnnouncementResource($announcement->load('creator'));
    }

    #[Authorize('message.announcement.delete')]
    public function destroy(Announcement $announcement): Response
    {
        $announcement->delete();

        return response()->noContent();
    }
}
