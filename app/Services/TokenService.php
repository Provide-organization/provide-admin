<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Emite e valida pares (access, refresh) assinados em RS256.
 *
 *   access_token  — curto (default 60min) — enviado no body da resposta,
 *                   mantido em memória no frontend.
 *   refresh_token — longo (default 14 dias) — devolvido em cookie HttpOnly
 *                   "provide_refresh" (SameSite=Lax, Path=/api/v1/auth).
 *
 * O refresh é um JWT também (mesma chave), diferenciado pelo claim `type`.
 */
class TokenService
{
    public const COOKIE_NAME      = 'provide_refresh';
    public const COOKIE_PATH      = '/api/v1/auth';
    public const REFRESH_TTL_DEFAULT_MIN = 20160; // 14 dias

    public function issuePair(Usuario $usuario): array
    {
        $access     = JWTAuth::customClaims(['type' => 'access'])->fromUser($usuario);
        $refreshTtl = (int) env('JWT_REFRESH_TTL', self::REFRESH_TTL_DEFAULT_MIN);

        // A lib php-open-source-saver/jwt-auth não tem setTTL no facade JWT;
        // fixamos o TTL do refresh via claim `exp` customizada (thread-safe).
        $refresh = JWTAuth::customClaims([
            'type' => 'refresh',
            'exp'  => now()->addMinutes($refreshTtl)->timestamp,
        ])->fromUser($usuario);

        return [
            'access_token'  => $access,
            'refresh_token' => $refresh,
            'token_type'    => 'bearer',
            'expires_in'    => auth('api')->factory()->getTTL() * 60,
            'refresh_ttl'   => $refreshTtl * 60,
        ];
    }

    /**
     * @throws JWTException se o token for inválido / expirado / tipo errado.
     */
    public function userFromRefresh(string $refreshToken): Usuario
    {
        try {
            JWTAuth::setToken($refreshToken);
            $payload = JWTAuth::getPayload();
        } catch (TokenExpiredException | TokenInvalidException $e) {
            throw new JWTException('Refresh token inválido ou expirado.');
        }

        if (($payload->get('type') ?? null) !== 'refresh') {
            throw new JWTException('Token não é de tipo refresh.');
        }

        /** @var Usuario|null $usuario */
        $usuario = JWTAuth::toUser($refreshToken);

        if (! $usuario) {
            throw new JWTException('Usuário do refresh token não encontrado.');
        }

        // Invalida este refresh (rotação: uso único).
        JWTAuth::invalidate($refreshToken);

        return $usuario;
    }

    public function invalidate(string $token): void
    {
        try {
            JWTAuth::invalidate($token);
        } catch (\Throwable $e) {
            // best-effort; se já estava na blacklist não há problema.
        }
    }

    public function buildRefreshCookie(string $refreshToken, int $ttlMinutes): Cookie
    {
        $domain = env('COOKIE_DOMAIN') ?: null;
        $secure = (bool) env('COOKIE_SECURE', false);

        return new Cookie(
            name: self::COOKIE_NAME,
            value: $refreshToken,
            expire: now()->addMinutes($ttlMinutes)->getTimestamp(),
            path: self::COOKIE_PATH,
            domain: $domain,
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public function buildForgetCookie(): Cookie
    {
        $domain = env('COOKIE_DOMAIN') ?: null;
        $secure = (bool) env('COOKIE_SECURE', false);

        return new Cookie(
            name: self::COOKIE_NAME,
            value: '',
            expire: 1,
            path: self::COOKIE_PATH,
            domain: $domain,
            secure: $secure,
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public function readRefreshCookie(Request $request): ?string
    {
        $value = $request->cookie(self::COOKIE_NAME);
        return is_string($value) && $value !== '' ? $value : null;
    }
}
