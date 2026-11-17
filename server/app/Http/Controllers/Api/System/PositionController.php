<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\{PositionIndexRequest, PositionRequest};
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class PositionController extends Controller
{
    #[Authorize('system.position.view')]
    public function index(PositionIndexRequest $request): AnonymousResourceCollection
    {
        $query = Position::query()->withCount('users')->filter($request->filters());

        return PositionResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    #[Authorize('system.position.create')]
    public function store(PositionRequest $request): PositionResource
    {
        return new PositionResource(Position::query()->create($request->validated()));
    }

    #[Authorize('system.position.view')]
    public function show(Position $position): PositionResource
    {
        return new PositionResource($position->loadCount('users'));
    }

    #[Authorize('system.position.update')]
    public function update(PositionRequest $request, Position $position): PositionResource
    {
        $position->update($request->validated());

        return new PositionResource($position);
    }

    #[Authorize('system.position.delete')]
    #[Authorize('delete', 'position')]
    public function destroy(Position $position): Response
    {
        $position->delete();

        return response()->noContent();
    }
}
