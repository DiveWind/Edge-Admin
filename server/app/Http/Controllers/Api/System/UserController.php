<?php

namespace App\Http\Controllers\Api\System;

use App\Actions\System\CreateUser;
use App\Actions\System\DeleteUser;
use App\Actions\System\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreUserRequest;
use App\Http\Requests\System\UpdateUserRequest;
use App\Http\Requests\System\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    /**
     * @var list<string>
     */
    protected array $relations = ['department', 'roles', 'positions'];

    /**
     * 用户列表（受数据权限约束）。
     */
    public function index(UserIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $query = User::query()
            ->with($this->relations)
            ->visibleTo($actor)
            ->filter($request->filters());

        return UserResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    public function store(StoreUserRequest $request, CreateUser $createUser): UserResource
    {
        $user = $createUser->handle($request->validated());

        return new UserResource($user->load($this->relations));
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load($this->relations));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        $updateUser->handle($user, $request->validated());

        return new UserResource($user->load($this->relations));
    }

    /**
     * 软删除并吊销其 token。
     */
    public function destroy(User $user, DeleteUser $deleteUser): Response
    {
        Gate::authorize('delete', $user);

        $deleteUser->handle($user);

        return response()->noContent();
    }
}
