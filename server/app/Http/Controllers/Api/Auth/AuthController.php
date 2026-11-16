<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /**
     * 账号密码登录，签发 Bearer Token。
     */
    public function login(LoginRequest $request, AttemptLogin $attemptLogin): JsonResponse
    {
        $token = $attemptLogin->handle(
            $request->string('username')->toString(),
            $request->string('password')->toString(),
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * 吊销当前 token。
     */
    public function logout(#[CurrentUser] User $user): Response
    {
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(#[CurrentUser] User $user): CurrentUserResource
    {
        return new CurrentUserResource($user);
    }
}
