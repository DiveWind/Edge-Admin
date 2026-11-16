<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitor\LoginLogIndexRequest;
use App\Http\Resources\LoginLogResource;
use App\Models\LoginLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LoginLogController extends Controller
{
    public function index(LoginLogIndexRequest $request): AnonymousResourceCollection
    {
        $query = LoginLog::query()->filter($request->filters());

        return LoginLogResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function destroy(LoginLog $loginLog): Response
    {
        $loginLog->delete();

        return response()->noContent();
    }
}
