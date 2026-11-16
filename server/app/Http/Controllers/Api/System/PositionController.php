<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\PositionIndexRequest;
use App\Http\Requests\System\PositionRequest;
use App\Http\Resources\PositionResource;
use App\Models\Position;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PositionController extends Controller
{
    public function index(PositionIndexRequest $request): AnonymousResourceCollection
    {
        $query = Position::query()->withCount('users')->filter($request->filters());

        return PositionResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(PositionRequest $request): PositionResource
    {
        return new PositionResource(Position::query()->create($request->validated()));
    }

    public function show(Position $position): PositionResource
    {
        return new PositionResource($position->loadCount('users'));
    }

    public function update(PositionRequest $request, Position $position): PositionResource
    {
        $position->update($request->validated());

        return new PositionResource($position);
    }

    public function destroy(Position $position): Response
    {
        Gate::authorize('delete', $position);

        $position->delete();

        return response()->noContent();
    }
}
