<?php

namespace App\Services;

use App\Exceptions\InactiveAccountException;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(private readonly TokenService $tokens) {}

    /**
     * @throws JWTException
     */
    public function login(string $identifier, string $password): array
    {
        if (! Auth::guard('api')->attempt([
            'email'    => $identifier,
            'password' => $password,
            'ativo'    => true,
        ])) {
            throw new JWTException('Credenciais inválidas ou usuário inativo.');
        }

        /** @var Usuario $usuario */
        $usuario = Auth::guard('api')->user();

        // O attempt acima emite um token "access" padrão — descartamos e usamos
        // o TokenService para emitir o par (access + refresh com claim type).
        Auth::guard('api')->logout();

        $pair = $this->tokens->issuePair($usuario);

        return array_merge($pair, [
            'user' => $this->buildUserPayload($usuario),
        ]);
    }

    /**
     * @throws JWTException
     */
    public function refresh(string $refreshToken): array
    {
        $usuario = $this->tokens->userFromRefresh($refreshToken);

        $usuario->refresh();
        if (! $usuario->ativo) {
            throw new InactiveAccountException;
        }

        $accessOnly = $this->tokens->issueAccessOnly($usuario);

        return array_merge($accessOnly, [
            'user' => $this->buildUserPayload($usuario),
        ]);
    }

    public function logout(?string $refreshToken = null): void
    {
        try {
            $accessToken = JWTAuth::getToken();
            if ($accessToken) {
                $this->tokens->invalidate((string) $accessToken);
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        if ($refreshToken) {
            $this->tokens->invalidate($refreshToken);
        }

        Auth::guard('api')->logout();
    }

    public function me(): array
    {
        /** @var Usuario $usuario */
        $usuario = Auth::guard('api')->user();

        return $this->buildUserPayload($usuario);
    }

    /**
     * Troca a senha do admin autenticado. Limpa deve_trocar_senha.
     *
     * @throws \InvalidArgumentException quando a senha atual não confere.
     */
    public function changePassword(string $senhaAtual, string $senhaNova): void
    {
        /** @var Usuario $usuario */
        $usuario = Auth::guard('api')->user();

        if (! $usuario || ! Hash::check($senhaAtual, $usuario->senha)) {
            throw new \InvalidArgumentException('Senha atual incorreta.');
        }

        $usuario->forceFill([
            'senha'             => Hash::make($senhaNova),
            'deve_trocar_senha' => false,
        ])->save();
    }

    private function buildUserPayload(Usuario $usuario): array
    {
        $usuario->loadMissing(['pessoa', 'perfis']);

        $roleNivel = $usuario->perfis->min('nivel') ?? 1;

        return [
            'id'                 => (string) $usuario->id,
            'name'               => $usuario->pessoa?->nome_completo ?? $usuario->email,
            'email'              => $usuario->email,
            'role'               => $roleNivel,
            'dept_id'            => null,
            'org_slug'           => null,
            'sub_type'           => 'platform_admin',
            'deve_trocar_senha'  => (bool) $usuario->deve_trocar_senha,
            'modules'            => [],
            // permissions é preenchido na Fase 5 (RBAC) via PermissionResolver
            'permissions'        => ['platform.*'],
            'perfis'             => $usuario->perfis->pluck('nome')->values(),
        ];
    }
}
