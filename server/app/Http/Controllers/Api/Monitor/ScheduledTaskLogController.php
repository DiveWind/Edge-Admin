<?php

namespace App\Http\Controllers\Api\Monitor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitor\ScheduledTaskLogIndexRequest;
use App\Http\Resources\ScheduledTaskLogResource;
use App\Models\ScheduledTaskLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduledTaskLogController extends Controller
{
    public function __invoke(ScheduledTaskLogIndexRequest $request): AnonymousResourceCollection
    {
        $query = ScheduledTaskLog::query()->filter($request->filters());

        return ScheduledTaskLogResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }
}
