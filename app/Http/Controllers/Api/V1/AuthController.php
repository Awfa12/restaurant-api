<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Dashboard sign-in (API-CONTRACT §4 Auth). Sanctum Bearer tokens, valid
 * sanctum.expiration minutes (7 days); one token per device.
 */
class AuthController extends Controller
{
    /**
     * POST /auth/login → { token, user: { name } }.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        $token = $user->createToken(
            'dashboard',
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration')),
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * POST /auth/logout: deletes only the token of this device → 204.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * GET /auth/me → { name }; 401 once the token has expired or been deleted.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
