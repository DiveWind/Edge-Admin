<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\{JsonResponse, Response};

/**
 * 登录签发 / 退出吊销 Sanctum Token。
 */
class TokenController extends Controller
{
    /**
     * 账号密码登录，签发 Bearer Token（失败次数限流见 LoginRequest）。
     */
    public function store(LoginRequest $request, AttemptLogin $attemptLogin): JsonResponse
    {
        $token = $request->authenticate($attemptLogin);

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
    public function destroy(#[CurrentUser] User $user): Response
    {
        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
