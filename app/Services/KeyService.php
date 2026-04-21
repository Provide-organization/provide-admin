<?php

namespace App\Services;

use App\Models\PlatformKey;
use Illuminate\Support\Str;

/**
 * Gera e persiste o par RSA usado para assinar tokens JWT (RS256).
 *
 * ATENÇÃO — arquitetura atual (dev): um único par compartilhado por ambiente,
 * em disco (`storage/keys/jwt-*.pem`), acessível ao admin-backend e à
 * instancia-backend via bind mount. Apenas a chave pública é registrada em
 * `platform_keys` (para consulta/JWKS). O `iss` do token é o slug da org e
 * é validado em runtime pelo middleware ValidateTokenIssuer na instância.
 *
 * Em produção, cada cliente recebe um container dedicado + par dedicado
 * (ver ARQUITETURA_MULTI_INSTANCIA.md — fase de isolamento de processo).
 */
class KeyService
{
    public const KEY_BITS = 2048;

    /**
     * Gera um novo par RSA. Retorna ['private' => PEM, 'public' => PEM, 'kid' => uuid].
     */
    public function generateKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => self::KEY_BITS,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new \RuntimeException('Falha ao gerar par RSA: ' . openssl_error_string());
        }

        if (! openssl_pkey_export($resource, $privatePem)) {
            throw new \RuntimeException('Falha ao exportar chave privada RSA.');
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || ! isset($details['key'])) {
            throw new \RuntimeException('Falha ao obter chave pública RSA.');
        }
        $publicPem = $details['key'];

        return [
            'private' => $privatePem,
            'public'  => $publicPem,
            'kid'     => (string) Str::uuid(),
        ];
    }

    /**
     * Registra (ou atualiza) a chave pública da plataforma em platform_keys.
     * A privada NÃO é armazenada aqui — fica em disco (storage/keys/).
     */
    public function registerPlatformPublicKey(string $kid, string $publicKeyPem): PlatformKey
    {
        return PlatformKey::updateOrCreate(
            ['kid' => $kid],
            [
                'owner_type'      => PlatformKey::OWNER_PLATFORM,
                'organizacao_id'  => null,
                'issuer'          => 'platform',
                'algorithm'       => PlatformKey::ALGO_RS256,
                'public_key'      => $publicKeyPem,
                'private_key_enc' => null,
                'created_at'      => now(),
            ]
        );
    }

    /**
     * Busca pública por kid (para validar tokens vindos de terceiros).
     */
    public function findPublicKey(string $kid): ?PlatformKey
    {
        return PlatformKey::query()->where('kid', $kid)->first();
    }
}
