<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

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

        return response()->json(['data' => $payload]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }

    public function me(): JsonResponse
    {
        return response()->json(['data' => $this->authService->me()]);
    }
}
