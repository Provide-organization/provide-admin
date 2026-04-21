<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InactiveAccountException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly TokenService $tokens,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $payload = $this->authService->login(
                $request->validated('identifier'),
                $request->validated('password'),
            );
        } catch (JWTException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'INVALID_CREDENTIALS',
            ], 401);
        }

        return $this->respondWithPair($payload);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $this->tokens->readRefreshCookie($request)
            ?? (string) $request->input('refresh_token', '');

        if ($refreshToken === '') {
            return response()->json([
                'message' => 'Refresh token ausente.',
                'code'    => 'REFRESH_MISSING',
            ], 401);
        }

        try {
            $payload = $this->authService->refresh($refreshToken);
        } catch (InactiveAccountException) {
            return response()
                ->json([
                    'message' => 'Conta desativada.',
                    'code'    => 'ACCOUNT_INACTIVE',
                ], 403)
                ->withCookie($this->tokens->buildForgetCookie());
        } catch (JWTException $e) {
            $response = response()->json([
                'message' => $e->getMessage(),
                'code'    => 'REFRESH_INVALID',
            ], 401);
            return $response->withCookie($this->tokens->buildForgetCookie());
        }

        return $this->respondWithPair($payload);
    }

    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $this->tokens->readRefreshCookie($request);
        $this->authService->logout($refreshToken);

        return response()
            ->json(['message' => 'Sessão encerrada com sucesso.'])
            ->withCookie($this->tokens->buildForgetCookie());
    }

    public function me(): JsonResponse
    {
        return response()->json(['data' => $this->authService->me()]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->changePassword(
                $request->validated('senha_atual'),
                $request->validated('senha_nova'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 'INVALID_CURRENT_PASSWORD',
            ], 422);
        }

        return response()->json([
            'message' => 'Senha alterada com sucesso.',
            'data'    => $this->authService->me(),
        ]);
    }

    /**
     * Move o refresh_token para cookie HttpOnly e devolve só o access no body.
     */
    private function respondWithPair(array $payload): JsonResponse
    {
        $refreshToken = $payload['refresh_token'] ?? null;
        $refreshTtlSeconds = $payload['refresh_ttl'] ?? (TokenService::REFRESH_TTL_DEFAULT_MIN * 60);

        unset($payload['refresh_token']);

        $response = response()->json(['data' => $payload]);

        if ($refreshToken) {
            $response->withCookie(
                $this->tokens->buildRefreshCookie($refreshToken, (int) ($refreshTtlSeconds / 60))
            );
        }

        return $response;
    }
}
