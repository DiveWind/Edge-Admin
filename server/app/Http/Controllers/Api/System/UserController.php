<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\ResetUserPasswordRequest;
use App\Http\Requests\System\StoreUserRequest;
use App\Http\Requests\System\UpdateUserRequest;
use App\Http\Requests\System\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /**
     * 用户列表（受数据权限约束）。
     */
    public function index(UserIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $query = User::query()
            ->with(['department', 'roles', 'positions'])
            ->visibleTo($actor)
            ->filter($request->filters());

        return UserResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::query()->create(Arr::except($request->validated(), ['role_ids', 'position_ids']));
            $user->roles()->sync($request->validated('role_ids'));
            $user->positions()->sync($request->validated('position_ids'));

            return $user;
        });

        return new UserResource($user->load(['department', 'roles', 'positions']));
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load(['department', 'roles', 'positions']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        DB::transaction(function () use ($request, $user): void {
            $user->update(Arr::except($request->validated(), ['role_ids', 'position_ids']));
            $user->roles()->sync($request->validated('role_ids'));
            $user->positions()->sync($request->validated('position_ids'));
        });

        return new UserResource($user->load(['department', 'roles', 'positions']));
    }

    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->noContent();
    }

    /**
     * 管理员重置密码，并吊销该用户已有 token。
     */
    public function resetPassword(ResetUserPasswordRequest $request, User $user): Response
    {
        DB::transaction(function () use ($request, $user): void {
            $user->update(['password' => $request->validated('password')]);
            $user->tokens()->delete();
        });

        return response()->noContent();
    }
}
