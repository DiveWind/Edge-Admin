<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitor\OnlineSessionIndexRequest;
use App\Http\Resources\OnlineSessionResource;
use App\Models\PersonalAccessToken;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * 在线会话：未过期的 Sanctum 访问令牌。
 */
class OnlineSessionController extends Controller
{
    public function index(OnlineSessionIndexRequest $request): AnonymousResourceCollection
    {
        $query = PersonalAccessToken::query()->with('user')->online()->filter($request->filters());

        return OnlineSessionResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    /**
     * 强制下线（吊销令牌）；不能踢掉当前会话（403）。
     */
    public function destroy(PersonalAccessToken $onlineSession): Response
    {
        Gate::authorize('delete', $onlineSession);

        $onlineSession->delete();

        return response()->noContent();
    }
}
