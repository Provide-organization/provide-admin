<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthService
{
    /**
     * @throws JWTException
     */
    public function login(string $identifier, string $password): array
    {
        $token = Auth::guard('api')->attempt([
            'email'    => $identifier,
            'password' => $password,
            'ativo'    => true,
        ]);

        if (! $token) {
            throw new JWTException('Credenciais inválidas ou usuário inativo.');
        }

        /** @var Usuario $usuario */
        $usuario = Auth::guard('api')->user();

        return [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            'user'         => $this->buildUserPayload($usuario),
        ];
    }

    public function logout(): void
    {
        Auth::guard('api')->logout();
    }

    public function me(): array
    {
        /** @var Usuario $usuario */
        $usuario = Auth::guard('api')->user();

        return $this->buildUserPayload($usuario);
    }

    private function buildUserPayload(Usuario $usuario): array
    {
        $usuario->loadMissing(['pessoa', 'perfis']);

        $roleNivel = $usuario->perfis->min('nivel') ?? 1;

        return [
            'id'      => (string) $usuario->id,
            'name'    => $usuario->pessoa?->nome_completo ?? $usuario->email,
            'email'   => $usuario->email,
            'role'    => $roleNivel,
            'dept_id' => null,
            'modules' => [],
        ];
    }
}
