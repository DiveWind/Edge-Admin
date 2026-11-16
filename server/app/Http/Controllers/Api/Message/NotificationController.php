<?php

namespace App\Http\Controllers\Api\Message;

use App\Http\Controllers\Controller;
use App\Http\Requests\Message\NotificationIndexRequest;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 我的通知（Laravel database notifications），只能操作自己的通知。
 */
class NotificationController extends Controller
{
    public function index(NotificationIndexRequest $request, #[CurrentUser] User $user): AnonymousResourceCollection
    {
        $query = $user->notifications()->getQuery()
            ->when($request->filled('read'), fn ($query) => $request->boolean('read')
                ? $query->whereNotNull('read_at')
                : $query->whereNull('read_at'));

        return NotificationResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function unreadCount(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => ['count' => $user->unreadNotifications()->count()]]);
    }

    public function markAsRead(string $id, #[CurrentUser] User $user): Response
    {
        $user->notifications()->findOrFail($id)->markAsRead();

        return response()->noContent();
    }

    public function markAllAsRead(#[CurrentUser] User $user): Response
    {
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function destroy(string $id, #[CurrentUser] User $user): Response
    {
        $user->notifications()->findOrFail($id)->delete();

        return response()->noContent();
    }
}
