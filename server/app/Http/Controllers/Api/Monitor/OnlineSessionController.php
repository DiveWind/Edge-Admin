<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitor\OnlineSessionIndexRequest;
use App\Http\Resources\OnlineSessionResource;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

/**
 * 在线会话：未过期的 Sanctum 访问令牌。
 */
class OnlineSessionController extends Controller
{
    #[Authorize('monitor.online-session.view')]
    public function index(OnlineSessionIndexRequest $request): AnonymousResourceCollection
    {
        $query = PersonalAccessToken::query()->with('user')->online()->filter($request->filters());

        return OnlineSessionResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    /**
     * 强制下线（吊销令牌）；不能踢掉当前会话（403）。
     */
    #[Authorize('monitor.online-session.delete')]
    #[Authorize('delete', 'online_session')]
    public function destroy(PersonalAccessToken $onlineSession): Response
    {
        $onlineSession->delete();

        return response()->noContent();
    }
}
