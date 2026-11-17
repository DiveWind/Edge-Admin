<?php

namespace App\Http\Controllers\Api\System;

use App\Actions\System\{CreateUser, DeleteUser, UpdateUser};
use App\Http\Controllers\Controller;
use App\Http\Requests\System\{StoreUserRequest, UpdateUserRequest, UserIndexRequest};
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

class UserController extends Controller
{
    /**
     * @var list<string>
     */
    protected array $relations = ['department', 'roles', 'positions'];

    /**
     * 用户列表（受数据权限约束）。
     */
    #[Authorize('system.user.view')]
    public function index(UserIndexRequest $request, #[CurrentUser] User $actor): AnonymousResourceCollection
    {
        $query = User::query()
            ->with($this->relations)
            ->visibleTo($actor)
            ->filter($request->filters());

        return UserResource::collection($request->applySort($query)->paginate($request->perPage())->withQueryString());
    }

    #[Authorize('system.user.create')]
    public function store(StoreUserRequest $request, CreateUser $createUser): UserResource
    {
        $user = $createUser->handle($request->validated());

        return new UserResource($user->load($this->relations));
    }

    #[Authorize('system.user.view')]
    #[Authorize('view', 'user')]
    public function show(User $user): UserResource
    {
        return new UserResource($user->load($this->relations));
    }

    #[Authorize('system.user.update')]
    #[Authorize('update', 'user')]
    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        $updateUser->handle($user, $request->validated());

        return new UserResource($user->load($this->relations));
    }

    /**
     * 软删除并吊销其 token。
     */
    #[Authorize('system.user.delete')]
    #[Authorize('delete', 'user')]
    public function destroy(User $user, DeleteUser $deleteUser): Response
    {
        $deleteUser->handle($user);

        return response()->noContent();
    }
}
